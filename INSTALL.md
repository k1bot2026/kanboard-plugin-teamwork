# Installation Guide

TeamWork adds multi-person task assignment to Kanboard. This guide covers installation, verification, and first-use setup.

## Requirements

- **Kanboard** >= 1.2.46
- **PHP** >= 7.4 (if installing manually)
- **Database**: SQLite, MySQL/MariaDB, or PostgreSQL
- **Docker** (if using the Docker method)

---

## Method 1: Docker (Recommended)

This approach builds a Kanboard image with the TeamWork plugin pre-installed from GitHub.

### Step 1: Create a Dockerfile

```dockerfile
FROM kanboard/kanboard:latest

RUN apk add --no-cache git \
    && git clone https://github.com/k1bot2026/kanboard-plugin-teamwork.git /var/www/app/plugins/TeamWork \
    && rm -rf /var/www/app/plugins/TeamWork/.git \
    && apk del git
```

This clones the plugin into the correct location and removes git afterward to keep the image small.

### Step 2: Create docker-compose.yml

```yaml
services:
  kanboard:
    build: .
    container_name: kanboard
    ports:
      - "8080:80"
    volumes:
      - kanboard_data:/var/www/app/data
    restart: unless-stopped

volumes:
  kanboard_data:
```

### Step 3: Build and start

```bash
docker compose up --build -d
```

### Step 4: Open Kanboard

Navigate to `http://localhost:8080` in your browser. The default credentials are:

- **Username**: admin
- **Password**: admin

The plugin creates its database tables automatically on first load.

---

## Method 2: Git Clone

Use this if you already have Kanboard installed on a server.

### Step 1: Clone into the plugins directory

```bash
cd /path/to/kanboard/plugins
git clone https://github.com/k1bot2026/kanboard-plugin-teamwork.git TeamWork
```

The folder **must** be named `TeamWork` (capital T, capital W). Kanboard uses the folder name to locate the plugin entry point.

### Step 2: Set file permissions

Make sure the web server user can read the plugin files:

```bash
chown -R www-data:www-data TeamWork/
```

Adjust the user (`www-data`) to match your web server configuration.

### Step 3: Restart your web server

```bash
# Apache
sudo systemctl restart apache2

# Nginx + PHP-FPM
sudo systemctl restart php-fpm
```

### Step 4: Open Kanboard

Refresh Kanboard in your browser. The plugin will be detected and its database tables created automatically.

---

## Method 3: ZIP Download

### Step 1: Download

Go to the [GitHub repository](https://github.com/k1bot2026/kanboard-plugin-teamwork) and click **Code > Download ZIP**, or download from the [Releases page](https://github.com/k1bot2026/kanboard-plugin-teamwork/releases).

### Step 2: Extract and rename

Extract the ZIP file into your Kanboard `plugins/` directory. Rename the extracted folder to `TeamWork`:

```bash
cd /path/to/kanboard/plugins
unzip kanboard-plugin-teamwork-main.zip
mv kanboard-plugin-teamwork-main TeamWork
```

### Step 3: Refresh Kanboard

Open Kanboard in your browser. The plugin loads automatically.

---

## Verification

After installation, confirm the plugin is active:

1. Log in to Kanboard
2. Go to **Settings** (gear icon, top-right) > **Plugins**
3. You should see **TeamWork** listed with:
   - **Author**: k1bot2026
   - **Version**: 1.0.0

If the plugin does not appear, see [Troubleshooting](#troubleshooting) below.

---

## Quick Start

### Assign people to a task

1. Open any task (click the task title on the board)
2. Find the **[+]** button in the Team Assignees section
3. Click it to open the search picker
4. Type a name to search for users, groups, or teams
5. Click a result to assign them

### Configure assignment mode

1. Open your project settings (gear icon in the project header)
2. In the left sidebar, click **Assignment Mode**
3. Choose one of:
   - **Equal Assignees** — everyone has equal status (default)
   - **Primary + Helpers** — first assignee is Primary, others are Helpers
   - **Custom Roles** — define your own roles (comma-separated)
4. Click **Save**

### Create project teams

1. Open your project settings
2. In the left sidebar, click **Team Management**
3. Enter a team name and click **Create**
4. Expand the team and use the search box to add members
5. You can now assign the entire team to a task in one click

### Set up automation rules

1. Open your project settings
2. In the left sidebar, click **Automation Rules**
3. Select a target column and choose who to auto-assign (a user or a team)
4. When a task moves to that column, the selected assignees are added automatically

---

## Troubleshooting

### Plugin not showing in the plugin list

- **Check the folder name.** It must be exactly `TeamWork` (case-sensitive). A folder named `teamwork`, `Teamwork`, or `kanboard-plugin-teamwork` will not be detected.
- **Check file permissions.** The web server user must be able to read `plugins/TeamWork/Plugin.php`.
- **Check PHP errors.** Look in your web server error log or Kanboard's `data/debug.log` for syntax or compatibility errors.

### Author or version shows "?"

This was fixed in version 1.0.0. If you see question marks, update to the latest version:

```bash
cd /path/to/kanboard/plugins/TeamWork
git pull origin main
```

For Docker, rebuild the image:

```bash
docker compose down
docker rmi <your-image-name>
docker compose up --build -d
```

### Database errors on first load

The plugin creates its tables automatically using Kanboard's migration system. If you see database errors:

- Verify your database user has CREATE TABLE permissions
- Check `data/debug.log` for the specific SQL error
- For Docker: `docker logs kanboard` shows container output

### Board shows "Internal Error"

If the board page shows an error after installing the plugin:

- Clear your browser cache
- Check `data/debug.log` for the specific error message
- Verify all plugin files were copied correctly (compare against the [repository file list](https://github.com/k1bot2026/kanboard-plugin-teamwork))

### Docker: plugin files not updating after rebuild

Docker may cache build layers. Force a clean rebuild:

```bash
docker compose down
docker rmi <your-image-name>
docker compose build --no-cache
docker compose up -d
```

---

## Updating

### Git clone installations

```bash
cd /path/to/kanboard/plugins/TeamWork
git pull origin main
```

Then refresh Kanboard. Database migrations run automatically if the schema changed.

### Docker installations

Rebuild the image to pull the latest plugin code:

```bash
docker compose down
docker rmi <your-image-name>
docker compose up --build -d
```

### ZIP installations

Download the latest ZIP, extract it, and replace the `TeamWork` folder. Refresh Kanboard.

---

## Uninstalling

1. Remove the `plugins/TeamWork/` directory
2. The plugin's database tables (`teamwork_task_assignees`, `teamwork_teams`, `teamwork_team_members`, `teamwork_automation_rules`) will remain but are harmless. Remove them manually if desired.

---

## License

MIT License. See [LICENSE](LICENSE) for details.
