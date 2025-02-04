# MembershipWorks Events to Posts

![License](https://img.shields.io/badge/license-Custom-blue)
![WordPress](https://img.shields.io/badge/wordpress-6.4%2B-green)
![PHP](https://img.shields.io/badge/php-7.4%2B-purple)

Automatically creates and updates WordPress posts from MembershipWorks events. Seamlessly sync your events with your WordPress site.

## 🚀 Features

- **Automatic Post Creation**: Creates WordPress posts from MembershipWorks events
- **Smart Updates**: Preserves post titles while updating content and details
- **Chronological Ordering**: Posts are created in order based on event dates
- **Featured Images**: Automatically sets event images as featured images
- **Event Registration**: Adds registration links with proper formatting
- **Post Cleanup**: Automatically handles outdated event posts
- **Custom User**: Creates a dedicated plugin user for better tracking

## 📋 Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- MembershipWorks account with API access
- Valid API Key and Organization ID

## 🔧 Installation

1. Upload the plugin files to `/wp-content/plugins/membershipworks-events-to-posts`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > MembershipWorks Events
4. Enter your MembershipWorks API Key and Organization ID
5. Configure your preferred settings:
   - Update frequency
   - Default post tags
   - Events base URL
   - Update behavior for existing posts
6. Click "Save Settings"

## ⚙️ Configuration

### API Credentials
To find your API credentials:
1. Log in to your MembershipWorks portal
2. Go to Organization Settings
3. Click on the Integrations tab
4. Find your API Key and Organization ID in the API section

### Plugin Settings
- **API Key**: Your MembershipWorks API key
- **Organization ID**: Your MembershipWorks organization identifier
- **Update Frequency**: How often to check for event changes
- **Default Tags**: Tags to add to all event posts
- **Events Base URL**: Base URL for event registration links
- **Update Existing Posts**: Whether to update or preserve existing posts

## 🔄 Updates

To receive plugin updates and access premium support:
1. Visit [wpplugins.symphony-ts.com](https://wpplugins.symphony-ts.com)
2. Create an account or log in
3. Register your plugin installation
4. Receive automatic update notifications

## 🛠️ Development

### Building from Source
```bash
# Install dependencies
npm install

# Start development build
npm run start

# Create production build
npm run build

# Create distribution package
npm run package
```

### Running Tests
```bash
# Run all tests
npm run test

# Run tests with coverage
npm run test:coverage

# Run tests in watch mode
npm run test:watch
```

## 📄 License

Copyright (c) 2025 Frankie Rodriguez <frankie@symphonytechsolutions.com>

This project is licensed under a custom license that prohibits commercial use without explicit written permission. See the [LICENSE.txt](LICENSE.txt) file for details.

## 🤝 Support

For premium support and feature requests:
- Visit [wpplugins.symphony-ts.com](https://wpplugins.symphony-ts.com)
- Email: support@symphony-ts.com

## 🔐 Security

Found a security issue? Please email security@symphony-ts.com instead of using the issue tracker.
