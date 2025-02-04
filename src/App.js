import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import './styles.css';
import {
    Card,
    CardBody,
    CardHeader,
    Button,
    TextControl,
    SelectControl,
    Notice,
    Panel,
    PanelBody,
    PanelRow,
    ExternalLink,
    ToggleControl
} from '@wordpress/components';

function App() {
    const [settings, setSettings] = useState({
        api_key: '',
        org: '',
        run_interval: 'daily',
        post_tags: '',
        post_format: 'standard',
        update_existing_posts: true,
        events_base_url: ''
    });
    const [isSaving, setIsSaving] = useState(false);
    const [isSyncing, setIsSyncing] = useState(false);
    const [notice, setNotice] = useState(null);

    useEffect(() => {
        fetchSettings();
    }, []);

    const fetchSettings = async () => {
        try {
            const response = await fetch(`${mwepSettings.apiUrl}settings`, {
                headers: {
                    'X-WP-Nonce': mwepSettings.apiNonce
                }
            });
            const data = await response.json();
            setSettings(data);
        } catch (error) {
            setNotice({
                status: 'error',
                message: __('Unable to load your settings. Please refresh the page.', 'mw-events-to-posts')
            });
        }
    };

    const saveSettings = async () => {
        setIsSaving(true);
        try {
            const response = await fetch(`${mwepSettings.apiUrl}settings`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': mwepSettings.apiNonce
                },
                body: JSON.stringify(settings)
            });
            
            if (response.ok) {
                setNotice({
                    status: 'success',
                    message: __('Your settings have been saved successfully!', 'mw-events-to-posts')
                });
            }
        } catch (error) {
            setNotice({
                status: 'error',
                message: __('Failed to save your settings. Please try again.', 'mw-events-to-posts')
            });
        }
        setIsSaving(false);
    };

    const syncEvents = async () => {
        setIsSyncing(true);
        try {
            const response = await fetch(`${mwepSettings.apiUrl}sync`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': mwepSettings.apiNonce
                }
            });
            
            if (response.ok) {
                setNotice({
                    status: 'success',
                    message: __('Event sync has started! Your posts will be updated shortly.', 'mw-events-to-posts')
                });
            }
        } catch (error) {
            setNotice({
                status: 'error',
                message: __('Failed to sync events. Please try again.', 'mw-events-to-posts')
            });
        }
        setIsSyncing(false);
    };

    const validateEventsBaseUrl = (url) => {
        if (!url) return false;
        try {
            const urlObj = new URL(url);
            return urlObj.protocol === 'http:' || urlObj.protocol === 'https:';
        } catch (e) {
            return false;
        }
    };

    const updateSetting = (key, value) => {
        if (key === 'events_base_url') {
            // Ensure URL ends with /events/#!event/
            const urlPattern = /\/events\/#!event\/?$/;
            if (!urlPattern.test(value)) {
                value = value.replace(/\/?$/, '/events/#!event/');
            }
        }
        setSettings({...settings, [key]: value});
    };

    const getPreviewUrl = () => {
        const baseUrl = settings.events_base_url || '';
        const sampleEventUrl = '2025/2/13/sample-event';
        return baseUrl + sampleEventUrl;
    };

    return (
        <div className="mwep-admin">
            <div className="mwep-header">
                <h1>{__('MembershipWorks Events', 'mw-events-to-posts')}</h1>
                <p className="mwep-description">
                    {__('This plugin automatically creates blog posts from your MembershipWorks events. Configure your settings below to get started.', 'mw-events-to-posts')}
                </p>
            </div>

            {notice && (
                <Notice 
                    status={notice.status}
                    onRemove={() => setNotice(null)}
                    isDismissible={true}
                >
                    {notice.message}
                </Notice>
            )}

            <div className="mwep-content">
                <Panel>
                    <PanelBody
                        title={__('MembershipWorks Connection', 'mw-events-to-posts')}
                        initialOpen={true}
                    >
                        <div className="mwep-connection-help">
                            <p>
                                {__('To connect with MembershipWorks, you\'ll need your API credentials. Here\'s how to find them:', 'mw-events-to-posts')}
                            </p>
                            <ol>
                                <li>{__('Log in to your MembershipWorks portal', 'mw-events-to-posts')}</li>
                                <li>{__('Go to Organization Settings', 'mw-events-to-posts')}</li>
                                <li>{__('Click on the Integrations tab', 'mw-events-to-posts')}</li>
                                <li>{__('Find your API Key and Organization ID in the API section', 'mw-events-to-posts')}</li>
                            </ol>
                            <ExternalLink href="https://membershipworks.com/organization/settings/integrations">
                                {__('Open MembershipWorks Settings →', 'mw-events-to-posts')}
                            </ExternalLink>
                        </div>

                        <PanelRow>
                            <TextControl
                                label={__('MembershipWorks API Key', 'mw-events-to-posts')}
                                help={__('Your secret API key from MembershipWorks', 'mw-events-to-posts')}
                                value={settings.api_key}
                                onChange={(value) => updateSetting('api_key', value)}
                            />
                        </PanelRow>

                        <PanelRow>
                            <TextControl
                                label={__('Organization ID', 'mw-events-to-posts')}
                                help={__('Your MembershipWorks organization identifier', 'mw-events-to-posts')}
                                value={settings.org}
                                onChange={(value) => updateSetting('org', value)}
                            />
                        </PanelRow>
                    </PanelBody>

                    <PanelBody
                        title={__('Post Settings', 'mw-events-to-posts')}
                        initialOpen={true}
                    >
                        <SelectControl
                            label={__('Update Frequency', 'mw-events-to-posts')}
                            value={settings.run_interval}
                            options={[
                                { label: __('Every hour', 'mw-events-to-posts'), value: 'hourly' },
                                { label: __('Twice a day', 'mw-events-to-posts'), value: 'twicedaily' },
                                { label: __('Once a day', 'mw-events-to-posts'), value: 'daily' }
                            ]}
                            onChange={(value) => updateSetting('run_interval', value)}
                        />
                        <TextControl
                            label={__('Default Post Tags', 'mw-events-to-posts')}
                            help={__('Enter tags separated by commas. These will be added to all event posts automatically.', 'mw-events-to-posts')}
                            value={settings.post_tags}
                            onChange={(value) => updateSetting('post_tags', value)}
                        />
                        <div className="mwep-events-url-field">
                            <TextControl
                                label={__('Events Base URL', 'mw-events-to-posts')}
                                help={__('Base URL for event registration links (e.g., https://yourdomain.com/events/#!event/)', 'mw-events-to-posts')}
                                value={settings.events_base_url}
                                onChange={(value) => updateSetting('events_base_url', value)}
                            />
                            {settings.events_base_url && (
                                <div className="mwep-url-preview">
                                    <p className="mwep-url-validation">
                                        {validateEventsBaseUrl(settings.events_base_url) ? (
                                            <span className="valid">{__('✓ Valid URL format', 'mw-events-to-posts')}</span>
                                        ) : (
                                            <span className="invalid">{__('⚠️ Invalid URL format', 'mw-events-to-posts')}</span>
                                        )}
                                    </p>
                                    <p className="mwep-preview-label">{__('Preview:', 'mw-events-to-posts')}</p>
                                    <code className="mwep-preview-url">{getPreviewUrl()}</code>
                                </div>
                            )}
                        </div>
                        <ToggleControl
                            label={__('Update Existing Posts', 'mw-events-to-posts')}
                            help={settings.update_existing_posts ? 
                                __('Posts will be updated when events change', 'mw-events-to-posts') : 
                                __('Changes to events will be ignored for existing posts', 'mw-events-to-posts')}
                            checked={settings.update_existing_posts}
                            onChange={(value) => updateSetting('update_existing_posts', value)}
                        />
                    </PanelBody>
                </Panel>

                <div className="mwep-actions">
                    <Button
                        variant="primary"
                        isBusy={isSaving}
                        onClick={saveSettings}
                    >
                        {__('Save Settings', 'mw-events-to-posts')}
                    </Button>

                    <Button
                        variant="secondary"
                        isBusy={isSyncing}
                        onClick={syncEvents}
                    >
                        {__('Update Events Now', 'mw-events-to-posts')}
                    </Button>
                </div>
            </div>
        </div>
    );
}

export default App;
