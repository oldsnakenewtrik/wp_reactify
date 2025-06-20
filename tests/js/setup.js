/**
 * Jest setup file for ReactifyWP JavaScript tests
 */

// Mock WordPress globals
global.wp = {
    i18n: {
        __: jest.fn((text) => text),
        _x: jest.fn((text) => text),
        _n: jest.fn((single, plural, number) => number === 1 ? single : plural),
        sprintf: jest.fn((format, ...args) => {
            return format.replace(/%[sd]/g, () => args.shift());
        })
    },
    element: {
        createElement: jest.fn(),
        Fragment: 'Fragment'
    },
    components: {
        Button: 'Button',
        Panel: 'Panel',
        PanelBody: 'PanelBody',
        PanelRow: 'PanelRow',
        TextControl: 'TextControl',
        SelectControl: 'SelectControl',
        ToggleControl: 'ToggleControl'
    },
    blocks: {
        registerBlockType: jest.fn()
    },
    data: {
        useSelect: jest.fn(),
        useDispatch: jest.fn()
    },
    apiFetch: jest.fn()
};

// Mock jQuery
global.$ = global.jQuery = jest.fn(() => ({
    ready: jest.fn(),
    on: jest.fn(),
    off: jest.fn(),
    trigger: jest.fn(),
    find: jest.fn(() => global.$()),
    addClass: jest.fn(() => global.$()),
    removeClass: jest.fn(() => global.$()),
    toggleClass: jest.fn(() => global.$()),
    attr: jest.fn(() => global.$()),
    prop: jest.fn(() => global.$()),
    val: jest.fn(() => global.$()),
    text: jest.fn(() => global.$()),
    html: jest.fn(() => global.$()),
    append: jest.fn(() => global.$()),
    prepend: jest.fn(() => global.$()),
    remove: jest.fn(() => global.$()),
    hide: jest.fn(() => global.$()),
    show: jest.fn(() => global.$()),
    fadeIn: jest.fn(() => global.$()),
    fadeOut: jest.fn(() => global.$()),
    slideUp: jest.fn(() => global.$()),
    slideDown: jest.fn(() => global.$()),
    ajax: jest.fn()
}));

// Mock ReactifyWP globals
global.reactifyWP = {
    ajaxUrl: '/wp-admin/admin-ajax.php',
    nonce: 'test-nonce',
    strings: {
        uploadSuccess: 'Upload successful',
        uploadError: 'Upload failed',
        deleteConfirm: 'Are you sure you want to delete this project?',
        processing: 'Processing...'
    },
    projects: []
};

global.reactifyWPAdmin = {
    ...global.reactifyWP,
    adminUrl: '/wp-admin/',
    currentScreen: 'settings_page_reactifywp'
};

// Mock browser APIs
global.FormData = class FormData {
    constructor() {
        this.data = new Map();
    }
    
    append(key, value) {
        this.data.set(key, value);
    }
    
    get(key) {
        return this.data.get(key);
    }
    
    has(key) {
        return this.data.has(key);
    }
};

global.File = class File {
    constructor(bits, name, options = {}) {
        this.bits = bits;
        this.name = name;
        this.type = options.type || '';
        this.size = bits.length || 0;
        this.lastModified = options.lastModified || Date.now();
    }
};

global.Blob = class Blob {
    constructor(bits = [], options = {}) {
        this.bits = bits;
        this.type = options.type || '';
        this.size = bits.reduce((size, bit) => size + (bit.length || 0), 0);
    }
};

// Mock fetch API
global.fetch = jest.fn(() =>
    Promise.resolve({
        ok: true,
        status: 200,
        json: () => Promise.resolve({}),
        text: () => Promise.resolve(''),
        blob: () => Promise.resolve(new Blob())
    })
);

// Mock URL API
global.URL = class URL {
    constructor(url, base) {
        this.href = url;
        this.origin = base || 'http://localhost';
        this.pathname = url.replace(this.origin, '');
    }
    
    static createObjectURL() {
        return 'blob:http://localhost/test-blob-url';
    }
    
    static revokeObjectURL() {
        // Mock implementation
    }
};

// Mock localStorage
const localStorageMock = {
    getItem: jest.fn(),
    setItem: jest.fn(),
    removeItem: jest.fn(),
    clear: jest.fn()
};
global.localStorage = localStorageMock;

// Mock sessionStorage
const sessionStorageMock = {
    getItem: jest.fn(),
    setItem: jest.fn(),
    removeItem: jest.fn(),
    clear: jest.fn()
};
global.sessionStorage = sessionStorageMock;

// Mock console methods for cleaner test output
global.console = {
    ...console,
    log: jest.fn(),
    warn: jest.fn(),
    error: jest.fn(),
    info: jest.fn(),
    debug: jest.fn()
};

// Mock window.location
delete window.location;
window.location = {
    href: 'http://localhost',
    origin: 'http://localhost',
    pathname: '/',
    search: '',
    hash: '',
    reload: jest.fn(),
    assign: jest.fn(),
    replace: jest.fn()
};

// Mock window.history
window.history = {
    pushState: jest.fn(),
    replaceState: jest.fn(),
    back: jest.fn(),
    forward: jest.fn(),
    go: jest.fn()
};

// Mock ResizeObserver
global.ResizeObserver = class ResizeObserver {
    constructor(callback) {
        this.callback = callback;
    }
    
    observe() {
        // Mock implementation
    }
    
    unobserve() {
        // Mock implementation
    }
    
    disconnect() {
        // Mock implementation
    }
};

// Mock IntersectionObserver
global.IntersectionObserver = class IntersectionObserver {
    constructor(callback, options) {
        this.callback = callback;
        this.options = options;
    }
    
    observe() {
        // Mock implementation
    }
    
    unobserve() {
        // Mock implementation
    }
    
    disconnect() {
        // Mock implementation
    }
};

// Setup and teardown
beforeEach(() => {
    // Clear all mocks before each test
    jest.clearAllMocks();
    
    // Reset fetch mock
    fetch.mockClear();
    
    // Reset localStorage and sessionStorage
    localStorageMock.getItem.mockClear();
    localStorageMock.setItem.mockClear();
    localStorageMock.removeItem.mockClear();
    localStorageMock.clear.mockClear();
    
    sessionStorageMock.getItem.mockClear();
    sessionStorageMock.setItem.mockClear();
    sessionStorageMock.removeItem.mockClear();
    sessionStorageMock.clear.mockClear();
});

afterEach(() => {
    // Clean up after each test
    jest.restoreAllMocks();
});
