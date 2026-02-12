/**
 * Connection Status Indicator
 * Visual indicator for WebSocket connection state
 * 
 * Usage in HTML:
 *   <div id="connection-status"></div>
 * 
 * Usage in JS:
 *   const statusIndicator = new ConnectionStatusIndicator('connection-status', websocketClient);
 */

class ConnectionStatusIndicator {
    constructor(elementId, websocketClient) {
        this.element = document.getElementById(elementId);
        this.ws = websocketClient;
        this.currentState = 'disconnected';
        
        if (!this.element) {
            console.error('Connection status element not found:', elementId);
            return;
        }
        
        // Initialize UI
        this.render();
        
        // Subscribe to WebSocket events
        this.setupListeners();
    }
    
    /**
     * Setup WebSocket event listeners
     */
    setupListeners() {
        if (!this.ws) return;
        
        this.ws.onConnected = () => this.updateState('connected');
        this.ws.onDisconnected = () => this.updateState('disconnected');
        this.ws.onReconnecting = (attempt) => this.updateState('reconnecting', attempt);
        this.ws.onError = () => this.updateState('error');
        
        this.ws.on('reconnect_failed', () => {
            this.updateState('failed');
        });
    }
    
    /**
     * Update connection state
     */
    updateState(state, extra = null) {
        this.currentState = state;
        this.render(extra);
    }
    
    /**
     * Render the status indicator
     */
    render(extra = null) {
        const states = {
            connected: {
                icon: '🟢',
                text: 'Connected',
                class: 'status-connected',
                color: '#10b981'
            },
            disconnected: {
                icon: '🔴',
                text: 'Disconnected',
                class: 'status-disconnected',
                color: '#ef4444'
            },
            reconnecting: {
                icon: '🟡',
                text: extra ? `Reconnecting (${extra}/${this.ws.options.maxReconnectAttempts})` : 'Reconnecting...',
                class: 'status-reconnecting',
                color: '#f59e0b'
            },
            error: {
                icon: '🔴',
                text: 'Connection Error',
                class: 'status-error',
                color: '#ef4444'
            },
            failed: {
                icon: '⚠️',
                text: 'Connection Failed',
                class: 'status-failed',
                color: '#dc2626'
            }
        };
        
        const state = states[this.currentState] || states.disconnected;
        
        this.element.innerHTML = `
            <div class="connection-status ${state.class}" title="WebSocket ${state.text}">
                <span class="status-dot" style="background-color: ${state.color}"></span>
                <span class="status-text">${state.text}</span>
            </div>
        `;
    }
    
    /**
     * Get current state
     */
    getState() {
        return this.currentState;
    }
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ConnectionStatusIndicator;
}
