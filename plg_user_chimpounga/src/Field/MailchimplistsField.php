<?php
/*
 * @package Chimpounga Plugin
 * @version 1.1
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3
 */
namespace Naftee\Plugin\User\Chimpounga\Field;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

use DrewM\MailChimp\MailChimp;

/**
 * This is a form element that displays Mailchimp Lists
 */
class MailchimplistsField extends ListField
  {

  protected function getOptions()
    {
    $plugin = PluginHelper::getPlugin('user', 'chimpounga');
       
    $params = new Registry($plugin->params);

    $options = [];  //The lists the user will be subscribed to

    if (!empty($params->get('apikey','')))
      {
      // Include Mailchimp API wrapper
      require_once JPATH_PLUGINS . '/user/chimpounga/lib/MailChimp.php';
            
      //Create a Mailchimp instance
      $mailchimp = null;
            
      try 
        {
        $mailchimp = new MailChimp($params->get('apikey',''));
        } 
      catch (\Exception $e) 
        {        
        $options[] = HTMLHelper::_('select.option', '-1', Text::alt('PLG_USER_CHIMPOUNGA_INVALID_API_KEY', preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->fieldname)));
        // Merge any additional options from the XML definition
        $options = array_merge(parent::getOptions(), $options);
        
        return $options;
        }  
              
      //Get all the mailing lists (with a get on the lists method)
      $lists = $mailchimp->get('lists');
      
      // API request failed
      if (!$mailchimp->success())
          {
          $options[] = HTMLHelper::_('select.option', '-1', Text::_('PLG_USER_CHIMPOUNGA_INVALID_API_KEY'));

          $options = array_merge(parent::getOptions(), $options);

          return $options;
          }

      // Valid request but no lists exist
      if (empty($lists['lists']))
          {
          $options[] = HTMLHelper::_('select.option', '-1', Text::_('PLG_USER_CHIMPOUNGA_NO_LISTS'));

          $options = array_merge(parent::getOptions(), $options);

          return $options;
          }

           
      if ($lists && !empty($lists['lists'])) 
        { //Display the dropdown so the user can choose which list
        foreach ($lists['lists'] as $list)
          {
          $options[] = HTMLHelper::_('select.option', $list['id'], $list['name']);
          }
        } 
      }
      
    //Create a translated option ensuring safe characters in the key    
    else
      {
      $options[] = HTMLHelper::_('select.option', '-1', Text::alt('PLG_USER_CHIMPOUNGA_PROVIDE_API_KEY', preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->fieldname)));
      }

    // Merge any additional options from the XML definition
    $options = array_merge(parent::getOptions(), $options);

    return $options;
    }
  }