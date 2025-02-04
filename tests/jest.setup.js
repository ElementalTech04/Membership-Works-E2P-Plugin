import '@testing-library/jest-dom';

// Mock WordPress i18n functions
global.__ = (text) => text;
global.wp = {
    element: {
        createElement: jest.fn(),
    },
};

// Mock the WordPress settings object
global.mwepSettings = {
    apiUrl: 'http://example.com/wp-json/mwep/v1/',
    apiNonce: 'test-nonce',
};
