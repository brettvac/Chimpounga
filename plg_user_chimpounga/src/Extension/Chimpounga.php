<?php
/**
 * @package     Joomla.Site
 * @subpackage  plg_user_chimpounga
 *
 * @version     1.2
 * @license     GNU General Public License version 2
 */

namespace Naftee\Plugin\User\Chimpounga\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Event\User\AfterSaveEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\SubscriberInterface;
use Naftee\Plugin\User\Chimpounga\Helper\MailchimpHelper;

/**
 * Chimpounga user plugin.
 *
 * Synchronizes Joomla user registrations with Mailchimp.
 *
 * @since  1.2
 */
final class Chimpounga extends CMSPlugin implements SubscriberInterface
{
    /**
     * Return the events this plugin subscribes to.
     *
     * @return  array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onUserAfterSave' => 'handleUserAfterSave',
        ];
    }

    /**
     * Synchronize a successfully saved Joomla user with Mailchimp.
     *
     * @param   AfterSaveEvent  $event  The user after-save event.
     *
     * @return  void
     */
    public function handleUserAfterSave(AfterSaveEvent $event): void
    {
        // Do not process unsuccessful user saves.
        if (!$event->getSavingResult()) {
            $this->logError($event->getErrorMessage());

            return;
        }

        // Retrieve the plugin parameters.
        $apiKey = $this->params->get('apikey');
        $listId = $this->params->get('listid');

        if (empty($apiKey) || empty($listId)) {
            $this->logError('Mailchimp API key or list ID is missing.');

            return;
        }

        // Retrieve the saved Joomla user.
        $user = $event->getUser();

        if (empty($user['email'])) {
            $this->logError('The Joomla user has no email address.');

            return;
        }

        // Split the user's name into first and last names.
        $trimName = trim($user['name']);

        if ($trimName === '') {
            $firstName = '';
            $lastName  = '';
        } else {
            $nameParts = preg_split('/\s+/', $trimName);

            $firstName = array_shift($nameParts);
            $lastName  = implode(' ', $nameParts);
        }

        // Prepare Mailchimp tags.
        $tags = [];

        // Add regular tags when enabled.
        if ($this->params->get('add_tags')) {
            $tags = array_merge(
                $tags,
                $this->parseTags($this->params->get('tags', ''))
            );
        }

        // Add HikaShop tags when enabled.
        if ($this->params->get('hikashop_confirmed_order_tags')) {
            $hikashopTagsInput = $this->params->get('hikashop_tags', '');

            if (!empty($hikashopTagsInput)) {
                try {
                    $db = Factory::getContainer()->get(DatabaseInterface::class);

                    // Find the corresponding HikaShop user ID.
                    $query = $db->getQuery(true)
                        ->select($db->quoteName('user_id'))
                        ->from($db->quoteName('#__hikashop_user'))
                        ->where(
                            $db->quoteName('user_cms_id') . ' = ' . (int) $user['id']
                        );

                    $db->setQuery($query);

                    $hikashopUserId = (int) $db->loadResult();

                    if ($hikashopUserId > 0) {
                        // Check whether the user has a confirmed order.
                        $query = $db->getQuery(true)
                            ->select('COUNT(*)')
                            ->from($db->quoteName('#__hikashop_order'))
                            ->where(
                                $db->quoteName('order_user_id') . ' = ' . $hikashopUserId
                            )
                            ->where(
                                $db->quoteName('order_status') . ' = ' . $db->quote('confirmed')
                            );

                        $db->setQuery($query);

                        $confirmedOrders = (int) $db->loadResult();

                        if ($confirmedOrders > 0) {
                            $tags = array_merge(
                                $tags,
                                $this->parseTags($hikashopTagsInput)
                            );
                        }
                    }
                } catch (\RuntimeException $e) {
                    $this->logError($e->getMessage());
                }
            }
        }

        // Initialize the Mailchimp helper.
        try {
            $mailchimp = new MailchimpHelper($apiKey);
        } catch (\Throwable $e) {
            $this->logError($e->getMessage());

            return;
        }

        // Calculate the subscriber hash.
        $subscriberHash = MailchimpHelper::subscriberHash($user['email']);

        $memberEndpoint = sprintf(
            'lists/%s/members/%s',
            rawurlencode($listId),
            $subscriberHash
        );

        // Check whether the subscriber already exists.
        $result = $mailchimp->get($memberEndpoint);

        // A 404 means the subscriber does not exist.
        // Any other failed request must stop processing.
        if (!$mailchimp->success()) {
            if (
                !is_array($result)
                || (int) ($result['status'] ?? 0) !== 404
            ) {
                $this->logError($mailchimp->getLastError());

                return;
            }
        }

        // Prepare the subscriber information.
        $memberData = [
            'merge_fields' => [
                'FNAME' => $firstName,
                'LNAME' => $lastName,
            ],
            'tags' => $tags,
        ];

        // Subscriber does not exist: create a new subscription.
        if ((int) ($result['status'] ?? 0) === 404) {
            $memberData['email_address'] = $user['email'];
            $memberData['status'] = 'subscribed';

            $mailchimp->post(
                sprintf('lists/%s/members', rawurlencode($listId)),
                $memberData
            );
        } elseif (($result['status'] ?? '') === 'subscribed') {
            // Existing subscriber: update their information.
            $mailchimp->patch(
                $memberEndpoint,
                $memberData
            );
        } elseif (($result['status'] ?? '') === 'unsubscribed') {
            // Previously unsubscribed: request confirmation again.
            $memberData['status'] = 'pending';

            $mailchimp->patch(
                $memberEndpoint,
                $memberData
            );
        } else {
            // No changes are required for other subscriber statuses.
            return;
        }

        // Log unsuccessful Mailchimp operations.
        if (!$mailchimp->success()) {
            $this->logError($mailchimp->getLastError());
        }
    }

    /**
     * Parse a comma-separated list of tags.
     *
     * @param   string  $input  Comma-separated tags.
     *
     * @return  array
     */
    private function parseTags(string $input): array
    {
        return array_values(
            array_filter(
                array_map('trim', explode(',', $input)),
                static fn($tag) => $tag !== ''
            )
        );
    }

    /**
     * Log an error to the Joomla log.
     *
     * @param   string|false|null  $message  Error message.
     *
     * @return  void
     */
    private function logError(string|false|null $message): void
    {
        if (empty($message)) {
            return;
        }

        Log::add($message, Log::ERROR, 'chimpounga-error');

        if (defined('JDEBUG') && JDEBUG) {
            Log::add($message, Log::DEBUG, 'chimpounga-error');
        }
    }
}
