<?php
/*
 * @package Chimpounga Plugin
 * @version 1.1
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */
namespace Naftee\Plugin\User\Chimpounga\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Event\User\AfterSaveEvent;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

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
  public function handleUserAfterSave(AfterSaveEvent $event): void
    {
    $success = $event->getSavingResult(); 

    if (!$success) 
      {
      $msg = $event->getErrorMessage();  
      Log::add($msg, Log::ERROR, 'chimpounga-error');

      if (JDEBUG)
        {
        Log::add($msg, Log::DEBUG, 'chimpounga-error');
        }
      
      return; //User save was not successful; do not continue processing
      }

    // Pull in the parameters    
    $apikey = $this->params->get('apikey');
    $listid = $this->params->get('listid');
    $tagsInput = $this->params->get('tags');
    $hikashopTagsInput = $this->params->get('hikashop_tags');
       
    // Trim the name to remove leading/trailing spaces
    $user = $event->getUser();
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
      
    // Tags must be an array for the Mailchimp submission
    $tags = [];
    
    // Add the tags for all saved users
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
    
    // Add the tags for users with a confirmed Hikashop order
    if (!empty($hikashopTagsInput))
    {
        try
        {
            // Get the dependency injection container
            $container = Factory::getContainer();

            // Get a database connection (a new instance of the DatabaseQuery class)
            $db = $container->get(DatabaseInterface::class);

            // Convert Joomla user ID to Hikashop user ID
            $query = $db->getQuery(true)
                ->select($db->quoteName('user_id'))
                ->from($db->quoteName('#__hikashop_user'))
                ->where(
                    $db->quoteName('user_cms_id') . ' = ' . (int) $user['id']
                );

            $db->setQuery($query);

            $hikashopUserId = (int) $db->loadResult();

            // Check whether the Hikashop user has a confirmed order
            if ($hikashopUserId > 0)
            {
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__hikashop_order'))
                    ->where(
                        $db->quoteName('order_user_id') . ' = ' . (int) $hikashopUserId
                    )
                    ->where(
                        $db->quoteName('order_status') . ' = ' . $db->quote('confirmed')
                    );

                $db->setQuery($query);

                $confirmedOrders = (int) $db->loadResult();

                // User has at least one confirmed order
                if ($confirmedOrders > 0)
                {
                    $hikashopTagsArray = explode(',', $hikashopTagsInput);

                    foreach ($hikashopTagsArray as $tag)
                    {
                        $tag = trim($tag);

                        if ($tag !== '')
                        {
                            $tags[] = $tag;
                        }
                    }
                }
            }
        }
        catch (\RuntimeException $e)
        {
            Log::add($e->getMessage(), Log::ERROR, 'chimpounga-error');

            if (JDEBUG)
            {
                Log::add($e->getMessage(), Log::DEBUG, 'chimpounga-error');
            }
        }
    }
    
    // Manually include the MailChimp library
    require_once JPATH_PLUGINS . '/user/chimpounga/lib/MailChimp.php';
    
    // Get the Mailchimp object
    try 
      {
      $mailchimp = new MailChimp($apikey);
      } 
    catch (\Exception $e) 
      {  
      Log::add($e->getMessage(), Log::ERROR, 'chimpounga-error');

      if (JDEBUG)
        {   
        Log::add($e->getMessage(), Log::DEBUG, 'chimpounga-error');
        }
      
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