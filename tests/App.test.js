import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import App from '../src/App';

describe('App Component', () => {
    beforeEach(() => {
        fetch.resetMocks();
    });

    it('renders the main heading', () => {
        render(<App />);
        expect(screen.getByText('MembershipWorks Events')).toBeInTheDocument();
    });

    it('loads settings on mount', async () => {
        const mockSettings = {
            api_key: 'test-key',
            org: 'test-org',
            run_interval: 'hourly',
            post_tags: 'test,tags'
        };

        fetch.mockResponseOnce(JSON.stringify(mockSettings));

        render(<App />);

        await waitFor(() => {
            expect(screen.getByLabelText(/API Key/i)).toHaveValue('test-key');
            expect(screen.getByLabelText(/Organization ID/i)).toHaveValue('test-org');
        });
    });

    it('saves settings when save button is clicked', async () => {
        const mockSettings = {
            api_key: '',
            org: '',
            run_interval: 'hourly',
            post_tags: 'membershipworks,homepage'
        };

        fetch.mockResponseOnce(JSON.stringify(mockSettings)); // For initial load
        fetch.mockResponseOnce(JSON.stringify({ success: true })); // For save response

        render(<App />);

        const apiKeyInput = screen.getByLabelText(/API Key/i);
        const orgInput = screen.getByLabelText(/Organization ID/i);
        const saveButton = screen.getByText(/Save Settings/i);

        await userEvent.type(apiKeyInput, 'new-api-key');
        await userEvent.type(orgInput, 'new-org');
        
        fireEvent.click(saveButton);

        await waitFor(() => {
            expect(fetch).toHaveBeenCalledWith(
                expect.stringContaining('/settings'),
                expect.objectContaining({
                    method: 'POST',
                    body: expect.stringContaining('new-api-key')
                })
            );
        });
    });

    it('shows success message after saving settings', async () => {
        fetch.mockResponseOnce(JSON.stringify({})); // For initial load
        fetch.mockResponseOnce(JSON.stringify({ success: true })); // For save response

        render(<App />);

        const saveButton = screen.getByText(/Save Settings/i);
        fireEvent.click(saveButton);

        await waitFor(() => {
            expect(screen.getByText(/settings have been saved/i)).toBeInTheDocument();
        });
    });

    it('triggers sync when sync button is clicked', async () => {
        fetch.mockResponseOnce(JSON.stringify({})); // For initial load
        fetch.mockResponseOnce(JSON.stringify({ success: true })); // For sync response

        render(<App />);

        const syncButton = screen.getByText(/Update Events Now/i);
        fireEvent.click(syncButton);

        await waitFor(() => {
            expect(fetch).toHaveBeenCalledWith(
                expect.stringContaining('/sync'),
                expect.objectContaining({
                    method: 'POST'
                })
            );
        });
    });
});
