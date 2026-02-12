/**
 * OpenClaw WebSocket Client
 * Real-time event streaming with auto-reconnect
 * 
 * Usage:
 *   const ws = new OpenClawWebSocket('ws://localhost:8080/ws/events');
 *   ws.on('agent_status', (data) => { console.log(data); });
 *   ws.on('heartbeat', (data) => { updateDashboard(data); });
 *   ws.connect();
 */

class OpenClawWebSocket {
    constructor(url, options = {}) {
        this.url = url;
        this.options = {
            autoReconnect: true,
            reconnectInterval: 3000,      // 3 seconds
            maxReconnectAttempts: 10,
            heartbeatInterval: 30000,     // 30 seconds ping
            debug: false,
            ...options
        };
        
        this.ws = null;
        this.reconnectAttempts = 0;
        this.reconnectTimer = null;
        this.heartbeatTimer = null;
        this.listeners = {};
        this.connected = false;
        this.manualDisconnect = false;
        
        // Connection status callbacks
        this.onConnected = null;
        this.onDisconnected = null;
        this.onError = null;
        this.onReconnecting = null;
    }
    
    /**
     * Connect to WebSocket server
     */
    connect() {
        this.manualDisconnect = false;
        
        try {
            this.log('Connecting to WebSocket:', this.url);
            this.ws = new WebSocket(this.url);
            
            this.ws.onopen = () => this.handleOpen();
            this.ws.onmessage = (event) => this.handleMessage(event);
            this.ws.onerror = (error) => this.handleError(error);
            this.ws.onclose = (event) => this.handleClose(event);
            
        } catch (error) {
            this.log('Connection error:', error);
            this.handleError(error);
            this.attemptReconnect();
        }
    }
    
    /**
     * Manually disconnect
     */
    disconnect() {
        this.manualDisconnect = true;
        this.connected = false;
        
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }
        
        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
            this.reconnectTimer = null;
        }
        
        if (this.ws) {
            this.ws.close(1000, 'Client disconnect');
            this.ws = null;
        }
        
        this.log('Disconnected');
    }
    
    /**
     * Send message to server
     */
    send(type, data) {
        if (!this.connected || !this.ws) {
            this.log('Cannot send - not connected');
            return false;
        }
        
        try {
            const message = JSON.stringify({ type, data, timestamp: Date.now() });
            this.ws.send(message);
            this.log('Sent:', type, data);
            return true;
        } catch (error) {
            this.log('Send error:', error);
            return false;
        }
    }
    
    /**
     * Subscribe to event type
     */
    on(eventType, callback) {
        if (!this.listeners[eventType]) {
            this.listeners[eventType] = [];
        }
        this.listeners[eventType].push(callback);
        this.log('Subscribed to:', eventType);
    }
    
    /**
     * Unsubscribe from event type
     */
    off(eventType, callback) {
        if (!this.listeners[eventType]) return;
        
        if (callback) {
            this.listeners[eventType] = this.listeners[eventType].filter(cb => cb !== callback);
        } else {
            delete this.listeners[eventType];
        }
    }
    
    /**
     * Emit event to listeners
     */
    emit(eventType, data) {
        if (!this.listeners[eventType]) return;
        
        this.listeners[eventType].forEach(callback => {
            try {
                callback(data);
            } catch (error) {
                this.log('Listener error:', error);
            }
        });
    }
    
    /**
     * Handle WebSocket open
     */
    handleOpen() {
        this.connected = true;
        this.reconnectAttempts = 0;
        this.log('Connected to WebSocket');
        
        // Start heartbeat
        this.startHeartbeat();
        
        // Notify connection
        if (this.onConnected) {
            this.onConnected();
        }
        
        this.emit('connected', { timestamp: Date.now() });
    }
    
    /**
     * Handle incoming message
     */
    handleMessage(event) {
        try {
            const message = JSON.parse(event.data);
            this.log('Received:', message.type, message.data);
            
            // Emit to specific event listeners
            if (message.type) {
                this.emit(message.type, message.data);
            }
            
            // Emit to general message listeners
            this.emit('message', message);
            
        } catch (error) {
            this.log('Parse error:', error);
        }
    }
    
    /**
     * Handle WebSocket error
     */
    handleError(error) {
        this.log('WebSocket error:', error);
        
        if (this.onError) {
            this.onError(error);
        }
        
        this.emit('error', error);
    }
    
    /**
     * Handle WebSocket close
     */
    handleClose(event) {
        this.connected = false;
        
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }
        
        this.log('Connection closed:', event.code, event.reason);
        
        if (this.onDisconnected) {
            this.onDisconnected(event);
        }
        
        this.emit('disconnected', { code: event.code, reason: event.reason });
        
        // Attempt reconnect if not manual disconnect
        if (!this.manualDisconnect && this.options.autoReconnect) {
            this.attemptReconnect();
        }
    }
    
    /**
     * Attempt to reconnect
     */
    attemptReconnect() {
        if (this.reconnectAttempts >= this.options.maxReconnectAttempts) {
            this.log('Max reconnect attempts reached');
            this.emit('reconnect_failed', { attempts: this.reconnectAttempts });
            return;
        }
        
        this.reconnectAttempts++;
        
        const delay = this.options.reconnectInterval * Math.min(this.reconnectAttempts, 5);
        this.log(`Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts}/${this.options.maxReconnectAttempts})`);
        
        if (this.onReconnecting) {
            this.onReconnecting(this.reconnectAttempts);
        }
        
        this.emit('reconnecting', { attempt: this.reconnectAttempts, delay });
        
        this.reconnectTimer = setTimeout(() => {
            this.connect();
        }, delay);
    }
    
    /**
     * Start heartbeat/ping
     */
    startHeartbeat() {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
        }
        
        this.heartbeatTimer = setInterval(() => {
            if (this.connected) {
                this.send('ping', { timestamp: Date.now() });
            }
        }, this.options.heartbeatInterval);
    }
    
    /**
     * Check if connected
     */
    isConnected() {
        return this.connected && this.ws && this.ws.readyState === WebSocket.OPEN;
    }
    
    /**
     * Get connection state
     */
    getState() {
        if (!this.ws) return 'disconnected';
        
        switch (this.ws.readyState) {
            case WebSocket.CONNECTING: return 'connecting';
            case WebSocket.OPEN: return 'connected';
            case WebSocket.CLOSING: return 'closing';
            case WebSocket.CLOSED: return 'disconnected';
            default: return 'unknown';
        }
    }
    
    /**
     * Debug logging
     */
    log(...args) {
        if (this.options.debug) {
            console.log('[OpenClawWS]', ...args);
        }
    }
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = OpenClawWebSocket;
}
