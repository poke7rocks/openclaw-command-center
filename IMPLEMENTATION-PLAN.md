# OpenClaw Command Center - Implementation Plan

## 1. Architecture Overview

### Components
```
┌─────────────────────────────────────────────────────────────────┐
│                     XAMPP Server (Windows PC)                   │
│  ┌─────────────────┐  ┌─────────────┐  ┌──────────────────┐   │
│  │   Web Frontend  │  │   PHP API   │  │   MySQL DB       │   │
│  │  (HTML/CSS/JS)  │  │   Gateway   │  │  Historical Data │   │
│  └────────┬────────┘  └──────┬──────┘  └──────────────────┘   │
│           │                   │                                  │
└───────────┼───────────────────┼──────────────────────────────────┘
            │                   │
            │    Tailscale      │
            │    Network        │
            │                   │
┌───────────┼───────────────────┼──────────────────────────────────┐
│           ▼                   ▼           VPS                    │
│  ┌─────────────────────────────────────┐                        │
│  │      OpenClaw Gateway API           │                        │
│  │   - REST Endpoints                  │                        │
│  │   - WebSocket Server                │                        │
│  │   - Agent Management                │                        │
│  └─────────────────────────────────────┘                        │
└──────────────────────────────────────────────────────────────────┘
```

### Data Flow
1. **Frontend → Backend**: AJAX calls to PHP API gateway
2. **PHP Gateway → OpenClaw**: HTTP requests over Tailscale
3. **Real-time Updates**: WebSocket connection for live data
4. **Historical Storage**: MySQL for metrics, logs, events

### Security Model
- **Authentication**: Session-based PHP auth with bcrypt passwords
- **Network**: Tailscale VPN for PC↔VPS communication
- **API Keys**: Stored in environment variables
- **CORS**: Restricted to localhost only
- **Input Validation**: PHP filter_var() for all inputs

## 2. Database Schema

### Tables

```sql
-- users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'viewer') DEFAULT 'viewer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- agents table
CREATE TABLE agents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    agent_name VARCHAR(100) NOT NULL,
    session_id VARCHAR(255) UNIQUE,
    status ENUM('active', 'idle', 'offline') DEFAULT 'offline',
    last_heartbeat TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    metadata JSON
);

-- heartbeats table
CREATE TABLE heartbeats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    agent_id INT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    response_time_ms INT,
    status VARCHAR(50),
    message TEXT,
    FOREIGN KEY (agent_id) REFERENCES agents(id)
);

-- metrics table
CREATE TABLE metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    metric_type VARCHAR(50) NOT NULL,
    metric_value DECIMAL(10,2),
    metric_unit VARCHAR(20),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    source VARCHAR(100),
    metadata JSON
);

-- gateway_logs table
CREATE TABLE gateway_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    level ENUM('info', 'warning', 'error') DEFAULT 'info',
    message TEXT,
    context JSON,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- todoist_tasks table
CREATE TABLE todoist_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    todoist_id VARCHAR(50) UNIQUE,
    content TEXT,
    priority INT,
    due_date DATE NULL,
    completed BOOLEAN DEFAULT FALSE,
    sync_status ENUM('synced', 'pending', 'error') DEFAULT 'pending',
    last_sync TIMESTAMP NULL,
    metadata JSON
);

-- api_usage table
CREATE TABLE api_usage (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service VARCHAR(50) NOT NULL,
    endpoint VARCHAR(255),
    tokens_used INT,
    cost_usd DECIMAL(10,6),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    metadata JSON
);

-- config_settings table
CREATE TABLE config_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## 3. API Endpoint Specification

### OpenClaw Gateway API Calls Needed

```yaml
# Agent Management
GET    /api/agents                     # List all agents
GET    /api/agents/{id}/status         # Get agent status
POST   /api/agents/{id}/heartbeat      # Send heartbeat command
POST   /api/agents/{id}/message        # Send message to agent
DELETE /api/agents/{id}                # Terminate agent

# Session Management
GET    /api/sessions                   # List active sessions
GET    /api/sessions/{id}/logs         # Get session logs
POST   /api/sessions/create            # Create new session

# Gateway Control
GET    /api/gateway/status             # Gateway health check
POST   /api/gateway/restart            # Restart gateway
GET    /api/gateway/config             # Get configuration
PUT    /api/gateway/config             # Update configuration

# Metrics & Usage
GET    /api/metrics/usage              # Token usage stats
GET    /api/metrics/costs              # Cost breakdown
GET    /api/metrics/performance        # Performance metrics

# WebSocket
WS     /ws/events                      # Real-time event stream
```

### PHP API Gateway Endpoints

```php
// api/v1/auth.php
POST   /api/v1/auth/login              # User login
POST   /api/v1/auth/logout             # User logout
GET    /api/v1/auth/verify             # Verify session

// api/v1/dashboard.php
GET    /api/v1/dashboard/overview      # Dashboard data
GET    /api/v1/dashboard/agents        # Agent statuses
GET    /api/v1/dashboard/metrics       # Recent metrics

// api/v1/agents.php
GET    /api/v1/agents/list             # List agents with status
POST   /api/v1/agents/heartbeat        # Trigger heartbeat
POST   /api/v1/agents/command          # Send command

// api/v1/todoist.php
GET    /api/v1/todoist/tasks           # Get tasks
POST   /api/v1/todoist/sync            # Sync with Todoist
PUT    /api/v1/todoist/task/{id}       # Update task

// api/v1/config.php
GET    /api/v1/config/list             # Get all settings
PUT    /api/v1/config/update           # Update setting

// api/v1/gateway.php
GET    /api/v1/gateway/status          # Gateway status
POST   /api/v1/gateway/control         # Control commands
```

## 4. File Structure

```
C:/xampp/htdocs/openclaw-command-center/
├── index.php                          # Main entry point
├── login.php                          # Login page
├── logout.php                         # Logout handler
├── config.php                         # Configuration file
├── .env                              # Environment variables
├── .htaccess                         # Apache config
│
├── api/
│   └── v1/
│       ├── auth.php                  # Authentication endpoints
│       ├── dashboard.php             # Dashboard data endpoints
│       ├── agents.php                # Agent management
│       ├── todoist.php               # Todoist integration
│       ├── config.php                # Config management
│       └── gateway.php               # Gateway control
│
├── includes/
│   ├── db.php                        # Database connection
│   ├── auth.php                      # Auth functions
│   ├── openclaw.php                  # OpenClaw API client
│   ├── websocket.php                 # WebSocket client
│   ├── helpers.php                   # Utility functions
│   └── validators.php                # Input validation
│
├── assets/
│   ├── css/
│   │   ├── main.css                  # Main stylesheet
│   │   ├── dashboard.css             # Dashboard styles
│   │   └── components.css            # Component styles
│   ├── js/
│   │   ├── app.js                    # Main application
│   │   ├── dashboard.js              # Dashboard logic
│   │   ├── agents.js                 # Agent management
│   │   ├── websocket.js              # WebSocket handler
│   │   ├── charts.js                 # Chart rendering
│   │   └── api-client.js             # API communication
│   └── img/
│       └── logo.png                  # Application logo
│
├── components/
│   ├── header.php                    # Page header
│   ├── sidebar.php                   # Navigation sidebar
│   ├── agent-card.php                # Agent status card
│   ├── metric-widget.php             # Metric display widget
│   └── footer.php                    # Page footer
│
├── pages/
│   ├── dashboard.php                 # Main dashboard
│   ├── agents.php                    # Agent management page
│   ├── todoist.php                   # Todoist integration
│   ├── config.php                    # Configuration page
│   ├── metrics.php                   # Metrics & costs page
│   ├── memory.php                    # Memory explorer
│   └── gateway.php                   # Gateway control
│
├── install/
│   ├── setup.php                     # Installation wizard
│   ├── schema.sql                    # Database schema
│   └── seed.sql                      # Initial data
│
└── logs/
    └── app.log                       # Application logs
```

## 5. Component Breakdown

### Frontend Components

#### A. Dashboard Widget System
```javascript
// assets/js/components/DashboardWidget.js
class DashboardWidget {
    constructor(config) {
        this.id = config.id;
        this.title = config.title;
        this.updateInterval = config.updateInterval || 30000;
        this.apiEndpoint = config.apiEndpoint;
    }
    
    render() { /* Render widget HTML */ }
    update() { /* Fetch and update data */ }
    destroy() { /* Cleanup */ }
}
```

#### B. Agent Status Cards
```javascript
// assets/js/components/AgentCard.js
class AgentCard {
    constructor(agent) {
        this.agent = agent;
        this.element = this.createElement();
    }
    
    createElement() { /* Create card HTML */ }
    updateStatus(status) { /* Update visual state */ }
    sendHeartbeat() { /* Trigger heartbeat */ }
}
```

#### C. Real-time Chart System
```javascript
// assets/js/components/MetricChart.js
class MetricChart {
    constructor(container, config) {
        this.chart = new Chart(container, {
            type: config.type || 'line',
            data: { datasets: [] },
            options: this.getChartOptions(config)
        });
    }
    
    addDataPoint(label, value) { /* Add new data */ }
    setTimeRange(range) { /* Update visible range */ }
}
```

### Backend Components

#### D. OpenClaw API Client
```php
// includes/openclaw.php
class OpenClawClient {
    private $baseUrl;
    private $tailscaleIp;
    
    public function __construct() {
        $this->baseUrl = getenv('OPENCLAW_API_URL');
        $this->tailscaleIp = getenv('TAILSCALE_IP');
    }
    
    public function getAgents() { /* GET /api/agents */ }
    public function sendHeartbeat($agentId) { /* POST /api/agents/{id}/heartbeat */ }
    public function getMetrics() { /* GET /api/metrics/usage */ }
    public function updateConfig($key, $value) { /* PUT /api/gateway/config */ }
}
```

#### E. WebSocket Handler
```php
// includes/websocket.php
class WebSocketClient {
    private $client;
    
    public function connect($url) { /* Connect to OpenClaw WS */ }
    public function onMessage($callback) { /* Handle incoming messages */ }
    public function broadcast($event, $data) { /* Send to all clients */ }
}
```

#### F. Task Sync Service
```php
// includes/todoist.php
class TodoistSync {
    private $apiKey;
    
    public function syncTasks() { /* Sync with Todoist API */ }
    public function updateTaskStatus($todoistId, $status) { /* Update task */ }
    public function getActiveTasks() { /* Get current tasks */ }
}
```

## 6. Agent Task List

### Phase 1: MVP (Tonight - 6 hours total)

#### Agent 1: Database Setup (30 min)
```bash
# Task: Create database and tables
1. Create database 'openclaw_cc'
2. Run install/schema.sql
3. Create admin user
4. Test connections
```

#### Agent 2: Backend Structure (45 min)
```bash
# Task: Setup PHP backend
1. Create directory structure
2. Implement includes/db.php
3. Implement includes/auth.php
4. Create config.php and .env template
5. Setup .htaccess for API routing
```

#### Agent 3: API Gateway (1.5 hours)
```bash
# Task: Build PHP API endpoints
1. Implement api/v1/auth.php (login/logout)
2. Implement api/v1/dashboard.php
3. Implement api/v1/agents.php
4. Create includes/openclaw.php client
5. Test API endpoints
```

#### Agent 4: Frontend Base (1 hour)
```bash
# Task: Create frontend structure
1. Build index.php with layout
2. Create login.php
3. Implement components/header.php
4. Implement components/sidebar.php
5. Setup assets/css/main.css
```

#### Agent 5: Dashboard UI (1.5 hours)
```bash
# Task: Build dashboard page
1. Create pages/dashboard.php
2. Implement assets/js/dashboard.js
3. Build agent status cards
4. Add basic metric widgets
5. Style with assets/css/dashboard.css
```

#### Agent 6: Real-time Updates (1 hour)
```bash
# Task: WebSocket integration
1. Implement assets/js/websocket.js
2. Create WebSocket connection handler
3. Update dashboard for live data
4. Add connection status indicator
```

#### Agent 7: Testing & Deploy (30 min)
```bash
# Task: Initial deployment
1. Test all API endpoints
2. Verify Tailscale connectivity
3. Setup installation script
4. Create README.md
5. Initial Git commit
```

### Phase 2: Enhanced Features (Morning - 4 hours)

#### Agent 8: Todoist Integration (1 hour)
```bash
# Task: Todoist sync
1. Implement includes/todoist.php
2. Create api/v1/todoist.php
3. Build pages/todoist.php UI
4. Add sync scheduler
```

#### Agent 9: Metrics & Charts (1 hour)
```bash
# Task: Advanced metrics
1. Implement Chart.js integration
2. Create pages/metrics.php
3. Build cost tracking widgets
4. Add usage graphs
```

#### Agent 10: Memory Explorer (45 min)
```bash
# Task: Memory browser
1. Create pages/memory.php
2. Build file tree viewer
3. Add markdown renderer
4. Implement search
```

#### Agent 11: Config Panel (45 min)
```bash
# Task: Configuration UI
1. Create pages/config.php
2. Build settings forms
3. Add validation
4. Implement save handlers
```

#### Agent 12: Gateway Controls (30 min)
```bash
# Task: Gateway management
1. Create pages/gateway.php
2. Add restart/status controls
3. Build log viewer
4. Add health monitoring
```

## 7. MVP vs Phase 2 Prioritization

### MVP (Launch by Morning)
- ✅ User authentication
- ✅ Basic dashboard with agent status
- ✅ Real-time heartbeat monitoring
- ✅ Simple agent control (heartbeat trigger)
- ✅ Basic metrics display
- ✅ WebSocket live updates
- ✅ Mobile-responsive design

### Phase 2 (Post-Launch)
- ⏳ Full Todoist integration
- ⏳ Advanced metrics with charts
- ⏳ Memory file explorer
- ⏳ Configuration management UI
- ⏳ Gateway control panel
- ⏳ Cost tracking dashboard
- ⏳ Communication hub
- ⏳ Agent orchestration tools
- ⏳ Export/backup features

## 8. Testing & Deployment Checklist

### Pre-Deployment Testing
```bash
# API Testing
[ ] Test auth endpoints (login/logout)
[ ] Test dashboard data retrieval
[ ] Test agent list and status
[ ] Test heartbeat trigger
[ ] Test WebSocket connection
[ ] Test error handling

# Security Testing
[ ] Verify session management
[ ] Test SQL injection prevention
[ ] Check XSS protection
[ ] Validate CORS headers
[ ] Test authentication bypass

# UI Testing
[ ] Test on Chrome/Firefox/Edge
[ ] Test mobile responsiveness
[ ] Test real-time updates
[ ] Test form validation
[ ] Test error messages
```

### Deployment Steps
```bash
1. Setup XAMPP on Windows PC
   - Install XAMPP
   - Configure MySQL
   - Set PHP timezone

2. Configure Tailscale
   - Install Tailscale on PC
   - Connect to VPS network
   - Test connectivity

3. Deploy Application
   - Clone from GitHub
   - Copy to htdocs/
   - Run install/setup.php
   - Configure .env file

4. Database Setup
   - Create database
   - Run schema.sql
   - Create admin user

5. Test Connectivity
   - Test OpenClaw API connection
   - Verify WebSocket connection
   - Check real-time updates

6. Final Configuration
   - Set API keys
   - Configure refresh intervals
   - Set timezone
   - Enable error logging

7. Launch Checklist
   [ ] All pages load without errors
   [ ] Login/logout works
   [ ] Dashboard shows agent data
   [ ] Real-time updates working
   [ ] No console errors
   [ ] Responsive on mobile
```

### Environment Variables (.env)
```env
# OpenClaw Configuration
OPENCLAW_API_URL=http://100.64.0.2:8888
TAILSCALE_IP=100.64.0.2

# Database Configuration
DB_HOST=localhost
DB_NAME=openclaw_cc
DB_USER=openclaw_user
DB_PASS=secure_password

# API Keys
TODOIST_API_KEY=your_todoist_key
BRAVE_API_KEY=your_brave_key

# Session Configuration
SESSION_LIFETIME=3600
SESSION_NAME=openclaw_session

# WebSocket Configuration
WS_URL=ws://100.64.0.2:8889/ws/events
WS_RECONNECT_INTERVAL=5000

# Application Settings
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_LOG_LEVEL=info
```

## Quick Start Commands

```bash
# Clone repository
git clone https://github.com/yourusername/openclaw-command-center.git

# Navigate to directory
cd C:/xampp/htdocs/openclaw-command-center

# Run installation
php install/setup.php

# Start XAMPP services
C:/xampp/xampp-control.exe

# Access application
http://localhost/openclaw-command-center/
```

## API Response Formats

### Standard Success Response
```json
{
    "success": true,
    "data": {
        // Response data here
    },
    "timestamp": "2024-02-12T03:45:00Z"
}
```

### Standard Error Response
```json
{
    "success": false,
    "error": {
        "code": "AUTH_FAILED",
        "message": "Invalid credentials"
    },
    "timestamp": "2024-02-12T03:45:00Z"
}
```

---

This implementation plan is ready for immediate agent execution. Each task is specific, self-contained, and can be worked on in parallel. The MVP can be built tonight with Phase 2 enhancements added in the morning.