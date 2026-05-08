<?php
/*
 * @package Chimpounga Plugin
 * Version 1.0
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3 only
 */
namespace Naftee\Plugin\User\Chimpounga\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\Event\Event;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Factory;

use DrewM\MailChimp\MailChimp;

defined('_JEXEC') or die; // No direct access

class Chimpounga extends CMSPlugin implements SubscriberInterface
  {
  
  public static function getSubscribedEvents(): array
    {
    //Map the Joomla event for the Chimpounga plugin to our method
    return [ 'onUserAfterSave' => 'handleUserAfterSave' ];
    }
    
   /*
    * Method is called after user data is stored in the database
    */
  public function handleUserAfterSave(Event $event): void
    {
    //extract the user data array, isNew flag, success status, and message from the event’s arguments array 
    [ $user, $isnew, $success, $msg ] = array_values($event->getArguments());       

    if (!$success) 
      {
      return; //User save was not successful
      }

    //Pull in the parameters    
    $apikey = $this->params->get('apikey');
    $listid = $this->params->get('listid');
    $tagsInput = $this->params->get('tags');
       
    // Trim the name to remove leading/trailing spaces
    $trimName = trim($user['name']);

    // Check if there are any spaces
    if ((substr_count($trimName, ' ')) == 0) 
      { // Only one name
      $firstName = $trimName;
      $lastName = '';
      } 
    else 
      {// Split the name for first and last name
      $name = explode(' ', $trimName);
      $firstName = $name[0] ?? '';
      $lastName = implode(' ', array_slice($name, 1)); //Handles multiple last names
      }
    
    //Create array from tags for Mailchimp subscription
    $tags = [];
    
    if (!empty($tagsInput)) 
      {
      $tagsArray = explode(',', $tagsInput);
    
      foreach ($tagsArray as $tag) 
        {
        $tag = trim($tag);
       
        if ($tag !== '') 
          { 
          $tags[] = $tag; // Just push the tag as a string
          }   
        }
      } 
        
    // Manually include the MailChimp library
    require_once JPATH_PLUGINS . '/user/chimpounga/lib/MailChimp.php';
    
    try 
      {
      $mailchimp = new MailChimp($apikey);
      } 
    catch (\Exception $e) 
      {  //You can never be too careful
      $application = Factory::getApplication();
      $application->enqueueMessage($e->getMessage(), 'error');
      return;
      } 

    //Check if the user's email already exists in our database    
    $subscriberHash = $mailchimp::subscriberHash($user['email']);
       
    $result = $mailchimp->get("lists/{$listid}/members/{$subscriberHash}");
    
    if ($result['status'] == 404)
      { //E-mail not found; subscribe the user.
      $mailchimp->post("lists/{$listid}/members", 
        [
        'email_address' => $user['email'],
        'status' => 'subscribed',
        'merge_fields' => ['FNAME' => $firstName, 'LNAME' => $lastName],
        'tags' => $tags,
        ]);
      }

    else if ($result['status'] == 'subscribed')
      { //E-mail exists in the database; update with new information
      $mailchimp->patch("lists/{$listid}/members/{$subscriberHash}",
        [
        'merge_fields' => ['FNAME' => $firstName, 'LNAME' => $lastName],
        'tags' => $tags,
        ]);
      }
      
    else if ($result['status'] == 'unsubscribed')
      { //E-mail exists but user unsubscribed; update and set to pending
      $mailchimp->patch("lists/{$listid}/members/{$subscriberHash}",
        [
        'status' => 'pending',
        'merge_fields' => ['FNAME' => $firstName, 'LNAME' => $lastName],
        'tags' => $tags,
        ]);
      }
         
    //Log an unsuccessful result
    if (!$mailchimp->success())
      {
      Log::add($mailchimp->getLastError(), Log::ERROR, 'chimpounga-error');
      if (JDEBUG)
        {
        Log::add($mailchimp->getLastError(), Log::DEBUG, 'chimpounga-error');
        }
      }
    }    
  }