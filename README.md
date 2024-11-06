# Gift Cards for WooCommerce® (free)

**Gift Cards for WooCommerce®** is a comprehensive plugin that adds robust gift card functionality to your WooCommerce® store. Empower your customers to purchase, send, and redeem gift cards effortlessly, enhancing their shopping experience and boosting your sales.

This plugin seamlessly integrates with WooCommerce®, providing both admin and customer-facing features to manage gift cards effectively. Whether you're running a small boutique or a large online store, this plugin offers the flexibility and tools you need to handle gift cards with ease.

## Features

- **Admin Management:**

    - Create, edit, and delete gift cards directly from the WooCommerce® admin dashboard.
    - View a comprehensive list of all gift cards with details such as code, balance, recipient email, issuance date, and expiration date.
    - Import and export gift cards in bulk using CSV files.
    - Log all gift card activities, including creation, usage, balance adjustments, and deletions.
    - Schedule automated emails for gift card delivery and expiration reminders.
- **Customer Experience:**

    - Customers can purchase gift cards as standalone products or as variations of existing products.
    - Option to send gift cards directly via email with personalized messages.
    - Manage and view active gift cards from the "My Account" section.
    - Apply gift card balances during checkout for discounts.
- **Integration:**

    - Seamlessly integrates with WooCommerce®'s cart and checkout processes.
    - Adds custom fields to products to mark them as gift cards.
    - Supports both digital and physical gift cards.
- **Customization:**

    - Customize email templates for gift card delivery and reminders.
    - Set expiration dates and reminder periods for gift cards.

## Installation

Follow these steps to install and activate the **Gift Cards for WooCommerce®** plugin:

1. **Download the Plugin:**

    - Clone the repository or download the latest release from [GitHub](https://github.com/robertdevore/gift-cards-for-woocommerce/).
2. **Upload to WordPress:**

    - Navigate to your WordPress dashboard.
    - Go to `Plugins` > `Add New` > `Upload Plugin`.
    - Click on `Choose File` and select the downloaded `.zip` file.
    - Click `Install Now`.
3. **Activate the Plugin:**

    - After installation, click `Activate Plugin`.
    - Ensure that WooCommerce® is active. If not, activate WooCommerce® first as this plugin depends on it.
4. **Initial Setup:**

    - Upon activation, the plugin will create necessary database tables.
    - Navigate to `WooCommerce` > `Gift Cards` to access the plugin's admin interface.

## Usage

### Admin Dashboard

Once activated, the plugin adds a new submenu under the WooCommerce® menu in your WordPress admin dashboard.

- **Accessing Gift Cards:**
    - Go to `WooCommerce` > `Gift Cards` to access the main management interface.
- **Tabs Overview:**
    - **Gift Cards:** View and manage all existing gift cards.
    - **Activity:** Monitor all gift card-related activities, including creation, usage, and adjustments.
    - **Add Card:** Issue new gift cards manually.
    - **Settings:** Customize email templates and set reminder periods for expiring gift cards.

### Managing Gift Cards

- **Viewing Gift Cards:**

    - The "Gift Cards" tab displays a list of all gift cards with details like code, balance, recipient email, issued date, and expiration date.
    - Use the search bar and filter options to locate specific gift cards.
- **Editing Gift Cards:**

    - Click the **Edit** button next to a gift card to modify its details.
    - A modal window will appear where you can update the balance, expiration date, recipient email, sender name, and personal message.
    - Upon successful submission, a success message will display within the modal.
- **Deleting Gift Cards:**

    - Click the **Delete** button next to a gift card to remove it.
    - A confirmation prompt ensures that you intend to delete the gift card.

### Importing and Exporting Gift Cards

- **Exporting Gift Cards:**
    - Navigate to the "Gift Cards" tab.
    - Click on the `Export CSV` button to download a CSV file containing all gift card data.
- **Importing Gift Cards:**
    - Click on the `Import CSV` button.
    - Select a valid CSV file containing gift card data.
    - The plugin will process the file and import the gift cards in batches to ensure performance.
    - Upon completion, a success or error message will display.

### Integrating Gift Cards with Products

- **Marking Products as Gift Cards:**

    - Edit a product in WooCommerce®.
    - In the "Product Data" section, check the **Gift Card** option to mark the product as a gift card.
    - For variable products, predefined gift card amounts (e.g., $25, $50, $100) are automatically generated as variations.
- **Gift Card Fields on Product Pages:**

    - On the frontend, gift card products display additional fields for:
        - **Gift Card Type:** Digital or Physical.
        - **Recipient Email:** Email address of the gift card recipient.
        - **Sender Name:** Name of the sender.
        - **Message:** Personalized message for the recipient.
        - **Delivery Date:** Date when the gift card should be delivered.

### Customer Experience

- **Purchasing Gift Cards:**

    - Customers can add gift card products to their cart and proceed to checkout.
    - During checkout, they can apply gift card codes to avail discounts based on their gift card balances.
- **Managing Gift Cards:**

    - After logging into their account, customers can navigate to the `Gift Cards` section under "My Account."
    - Here, they can view all active gift cards, their balances, and expiration dates.

### Additional Features

- **Scheduled Emails:**

    - The plugin schedules daily events to send out gift card delivery emails and expiration reminders.
    - Customize email templates and set the number of days before expiration to receive reminders in the "Settings" tab.
- **Activity Logs:**

    - Monitor all actions related to gift cards in the "Activity" tab, including creation, usage, balance adjustments, and deletions.

## Frequently Asked Questions (FAQ)

**Q: Does this plugin support both digital and physical gift cards?**  
**A:** Yes, the plugin allows you to create both digital and physical gift cards. You can specify the type when issuing a gift card.

**Q: Can I customize the email templates sent to recipients?**  
**A:** Absolutely! Navigate to the "Settings" tab in the Gift Cards admin page to customize the email templates, including adding custom images and text.

**Q: How does the import/export functionality work?**  
**A:** You can export all gift card data as a CSV file for backup or migration purposes. Similarly, you can import gift cards in bulk by uploading a properly formatted CSV file. The plugin processes imports and exports in batches to optimize performance.

**Q: Are there any user role restrictions?**  
**A:** Only users with the `manage_woocommerce` capability can manage gift cards. This ensures that only authorized personnel can create, edit, or delete gift cards.

**Q: How are gift card balances managed and updated?**  
**A:** Gift card balances are automatically updated based on usage during checkout. Admins can also manually adjust balances through the edit modal in the admin dashboard.

**Q: What happens if a gift card expires?**  
**A:** The plugin can be configured to send reminder emails a specified number of days before a gift card expires. Expired gift cards are no longer usable for discounts.

## Contributing

Contributions are always welcome! If you'd like to contribute to the free **Gift Cards for WooCommerce®** plugin, please follow these steps:

1. **Fork the Repository:**

    - Click the `Fork` button on the top right of the repository page.
2. **Clone Your Fork:**
    ```
    git clone https://github.com/your-username/gift-cards-for-woocommerce.git
    ```

3. **Create a New Branch:**
    ```
    git checkout -b feature/your-feature-name
    ```

4. **Make Your Changes:**

    - Implement your feature or fix.
5. **Commit Your Changes:**
    ```
    git commit -m "Add feature: Your Feature Description"
    ```

6. **Push to Your Fork:**
    ```
    git push origin feature/your-feature-name
    ```

7. **Submit a Pull Request:**

    - Navigate to the original repository and click `New Pull Request`.
    - Provide a clear description of your changes.

## License

This plugin is licensed under the [GPL-2.0+ License](http://www.gnu.org/licenses/gpl-2.0.txt). You may not remove this notice or any other from the source code.