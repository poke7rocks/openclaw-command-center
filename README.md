# OpenClaw Command Center

Web-based dashboard for monitoring and controlling OpenClaw agents in real-time.

## Features

### MVP (Phase 1)
- 🎛️ Real-time agent monitoring
- 💓 Heartbeat management & tracking
- 📊 Basic metrics dashboard
- 🔄 WebSocket live updates
- 🔐 Secure authentication
- 📱 Mobile-responsive design

### Phase 2 (Planned)
- ✅ Todoist task integration
- 📈 Advanced cost/usage charts
- 📝 Memory file explorer
- ⚙️ Configuration management UI
- 🎮 Gateway control panel
- 💬 Communication hub

## Tech Stack

- **Frontend:** HTML, CSS (Tailwind), JavaScript
- **Backend:** PHP 8.x
- **Database:** MySQL 8.x
- **Server:** XAMPP (Windows)
- **Network:** Tailscale VPN
- **Real-time:** WebSocket

## Quick Start

See [IMPLEMENTATION-PLAN.md](IMPLEMENTATION-PLAN.md) for full setup details.

```bash
# 1. Clone repository
git clone https://github.com/yourusername/openclaw-command-center.git

# 2. Copy to XAMPP htdocs
cp -r openclaw-command-center C:/xampp/htdocs/

# 3. Run installation
php install/setup.php

# 4. Access dashboard
http://localhost/openclaw-command-center/
```

## Build Status

See [STATUS.md](STATUS.md) for current build progress.

## Documentation

- [Implementation Plan](IMPLEMENTATION-PLAN.md) - Full architecture & specs
- [Build Logs](build-logs/) - Individual agent progress logs
- [API Documentation](docs/API.md) - Coming soon

## License

MIT
