<?php
/**
 * @package     Joomla.Site
 * @subpackage  plg_user_chimpounga
 *
 * @version     1.2
 * @license     GNU General Public License version 2
 */

namespace Naftee\Plugin\User\Chimpounga\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
use Naftee\Plugin\User\Chimpounga\Helper\MailchimpHelper;

/**
 * Form field displaying available Mailchimp mailing lists.
 *
 * @since  1.2
 */
class MailchimplistsField extends ListField
{
    /**
     * Get the available Mailchimp mailing lists.
     *
     * @return  array
     */
    protected function getOptions(): array
    {
        $options = [];

        // Retrieve the Chimpounga plugin configuration.
        $plugin = PluginHelper::getPlugin('user', 'chimpounga');

        if (!$plugin || empty($plugin->params)) {
            $options[] = HTMLHelper::_(
                'select.option',
                '-1',
                Text::alt(
                    'PLG_USER_CHIMPOUNGA_PROVIDE_API_KEY',
                    preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->fieldname)
                )
            );

            return array_merge(parent::getOptions(), $options);
        }

        $params = new Registry($plugin->params);

        $apiKey = $params->get('apikey', '');

        // API key has not been provided.
        if (empty($apiKey)) {
            $options[] = HTMLHelper::_(
                'select.option',
                '-1',
                Text::alt(
                    'PLG_USER_CHIMPOUNGA_PROVIDE_API_KEY',
                    preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->fieldname)
                )
            );

            return array_merge(parent::getOptions(), $options);
        }

        // Initialize the Mailchimp helper.
        try {
            $mailchimp = new MailchimpHelper($apiKey);
        } catch (\Throwable $e) {
            Log::add(
                $e->getMessage(),
                Log::ERROR,
                'chimpounga-error'
            );

            if (defined('JDEBUG') && JDEBUG) {
                Log::add(
                    $e->getMessage(),
                    Log::DEBUG,
                    'chimpounga-debug'
                );
            }

            $options[] = HTMLHelper::_(
                'select.option',
                '-1',
                Text::alt(
                    'PLG_USER_CHIMPOUNGA_INVALID_API_KEY',
                    preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->fieldname)
                )
            );

            return array_merge(parent::getOptions(), $options);
        }

        // Retrieve the available mailing lists.
        $lists = $mailchimp->get('lists');

        // Mailchimp API request failed.
        if (!$mailchimp->success()) {
            Log::add(
                (string) $mailchimp->getLastError(),
                Log::ERROR,
                'chimpounga-error'
            );

            $options[] = HTMLHelper::_(
                'select.option',
                '-1',
                Text::_('PLG_USER_CHIMPOUNGA_INVALID_API_KEY')
            );

            return array_merge(parent::getOptions(), $options);
        }

        // API request succeeded, but no mailing lists exist.
        if (empty($lists['lists'])) {
            $options[] = HTMLHelper::_(
                'select.option',
                '-1',
                Text::_('PLG_USER_CHIMPOUNGA_NO_LISTS')
            );

            return array_merge(parent::getOptions(), $options);
        }

        // Populate the dropdown with available mailing lists.
        foreach ($lists['lists'] as $list) {
            $options[] = HTMLHelper::_(
                'select.option',
                $list['id'],
                $list['name']
            );
        }

        // Merge any additional options from the XML definition.
        return array_merge(parent::getOptions(), $options);
    }
}
