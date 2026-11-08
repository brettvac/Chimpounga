# Chimpounga – Joomla! User Plugin
![Chimpounga Thumbnail](Chimpounga.png)

Chimpounga is a Joomla! user plugin that synchronizes Joomla! user accounts with a Mailchimp audience. Once installed and configured, it automatically adds or updates Mailchimp contacts whenever a Joomla! user account is successfully saved.

The plugin also supports Mailchimp tags, including optional tags assigned to customers who have placed at least one confirmed order through HikaShop.

## Requirements

- Joomla! 5.0.0 or later
- PHP 8.1.0 or later
- A Mailchimp account and API key
- HikaShop (optional, only required for confirmed-order tagging)

## Features

- **Automatic synchronization** – Adds new Joomla! users to a selected Mailchimp audience and updates existing contacts when their Joomla! account is saved.
- **Contact information updates** – Synchronizes the user's email address, first name, and last name.
- **Mailchimp tags** – Optionally assigns configured tags to contacts.
- **Subscription handling** – Adds new contacts with a subscribed status. Existing subscribed contacts are updated, while unsubscribed contacts are moved to pending status so they must confirm their subscription again.
- **HikaShop integration** – Optionally assigns specific Mailchimp tags to users who have at least one confirmed HikaShop order.
- **Dynamic audience selection** – Retrieves available Mailchimp audiences through the API, allowing you to select the desired audience directly in the plugin settings.
- **Debug logging** – Records Mailchimp API responses and request errors when Joomla! debugging is enabled.

## Installation

## Installation

Chimpounga can be installed directly from its latest GitHub release using Joomla!'s **Install from URL** feature.

1. In Joomla! Administrator, navigate to **System → Install → Extensions**.
2. Select the **Install from URL** tab.
3. Enter the following URL:

   `https://github.com/brettvac/Chimpounga/releases/latest/download/plg_user_chimpounga.zip`

4. Click **Check & Install** and wait for Joomla! to complete the installation.
5. Navigate to **System → Manage → Plugins**.
6. Find and enable **User - Chimpounga**.
7. Open the plugin settings and configure your Mailchimp API key and audience.

## Configuration

### Mailchimp API settings

**API Key**

Enter your Mailchimp API key. The plugin uses this key to communicate with the Mailchimp API.

**Mailchimp Audience**

Once a valid API key is entered, the audience selection field retrieves the available audiences from your Mailchimp account. Select the audience to which Joomla! users should be synchronized.

### Mailchimp tags

**Add Tags**

Choose whether to assign the configured tags to synchronized contacts.

**Tags**

Enter a comma-separated list of Mailchimp tags to assign to contacts. This field is available when Add Tags is enabled.

### HikaShop integration

**HikaShop Confirmed Order Tags**

Enable this option to assign additional Mailchimp tags to users who have at least one confirmed HikaShop order.

**HikaShop Tags**

Enter a comma-separated list of tags to assign to qualifying customers. This field is available when HikaShop Confirmed Order Tags is enabled.

Chimpounga checks the Joomla! user associated with the HikaShop account and verifies whether at least one order has the `confirmed` status before applying these tags.

## How synchronization works

Chimpounga listens for Joomla!'s `onUserAfterSave` event.

After a successful user save, the plugin:

1. Retrieves the Joomla! user's email address and name.
2. Checks the selected Mailchimp audience for an existing contact.
3. Adds the contact if it does not already exist.
4. Updates the contact's merge fields and configured tags if the contact is already subscribed.
5. Sets an unsubscribed contact to pending status, requiring the user to confirm their subscription again.
6. Checks for confirmed HikaShop orders, if that integration is enabled, and applies the corresponding tags.

Failed Joomla! user saves are ignored. Contacts with other Mailchimp subscription statuses are not automatically changed.

**Important:** New contacts are added with a subscribed status. Make sure your registration and consent process complies with applicable email marketing regulations and Mailchimp's requirements.

## Debugging and logging

Chimpounga records Mailchimp API request results and errors in Joomla!'s logging system.

To enable debug logging:

1. Enable **Debug System** in Joomla!'s Global Configuration.
2. Trigger a user registration or account update.
3. Check Joomla!'s logs for entries in the `chimpounga-debug` category.

The debug log includes HTTP response codes, including 404 responses when a contact is not found. These 404 responses are expected during the lookup of contacts that need to be created.

API keys, email addresses, and request payloads are not included in the debug messages.

## Version history

### Version 1.2

- Replaced the DrewM Mailchimp library with a dedicated helper using Joomla! CMS HttpFactory for API communication.
- Updated the main plugin and custom Mailchimp audience field to use the new helper.
- Improved Mailchimp API response handling, including contact lookup responses with HTTP 404 status codes.
- Added debug logging for Mailchimp API responses and request errors.
- Updated PHP file docblocks to follow Joomla! coding conventions.

## FAQ

**Q: Does Chimpounga send emails?**

A: No. Chimpounga synchronizes Joomla! user accounts with Mailchimp. Mailchimp handles subscription confirmations and email campaigns.

**Q: Does

**Q: This plugin is awesome! Can I send a donation?**  
**A:** Sure! Send your cryptonation to the following wallets:

`BTC 1PXWZJcBfehqgV25zWdVDS6RF2yVMxFkZD`

`Eth 0xC9b695D4712645Ba178B4316154621B284e2783D`

**Q: Got any more awesome Joomla! plugins?**  
**A:** Find them [right here](https://naftee.com)
