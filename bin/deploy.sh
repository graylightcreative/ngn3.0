#!/bin/bash

# ==============================================================================
# NGN 2.0.1 - Automation & Deployment Script
# ==============================================================================
# This script uses environment variables from .env to automate remote tasks
# such as status checks, database backups, and repository syncing.
#
# Prerequisites:
# - .env file with SSH_CONNECTION_STRING, SSH_PASSWORD, DB_*, and GITHUB_*
# - 'sshpass' installed for automated password entry (optional, but recommended)
# ==============================================================================

# Load environment variables safely
if [ -f .env ]; then
    # Use a more robust way to load .env that handles spaces
    set -a
    source .env
    set +a
else
    echo "Error: .env file not found."
    exit 1
fi

# Configuration
# Remove 'sh ' prefix if it exists in the connection string from .env
CLEAN_SSH_CONN=$(echo $SSH_CONNECTION_STRING | sed 's/^sh //')
REMOTE_PROJECT_ROOT="/www/wwwroot/nextgennoise"

# --- Functions ---

# Execute a command on the remote server via SSH
remote_exec() {
    local cmd=$1
    echo "Executing remote command: $cmd"
    if command -v sshpass >/dev/null 2>&1 && [ ! -z "$SSH_PASSWORD" ]; then
        sshpass -p "$SSH_PASSWORD" ssh -o StrictHostKeyChecking=no $CLEAN_SSH_CONN "$cmd"
    else
        ssh -o StrictHostKeyChecking=no $CLEAN_SSH_CONN "$cmd"
    fi
}

# Check remote server status
check_status() {
    echo "--- Remote Server Status ---"
    remote_exec "uptime; df -h /; php -v | head -n 1"
    echo "--- Web Server Status ---"
    remote_exec "systemctl status nginx | grep Active || systemctl status apache2 | grep Active"
}

# Backup remote database
backup_db() {
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local backup_file="ngn_prod_backup_$timestamp.sql"
    echo "Creating remote database backup: $backup_file"
    
    # We use DB_USER and DB_PASS from .env assuming they match the remote settings
    # or you can override these variables for the remote environment.
    remote_exec "mysqldump -h $DB_HOST -u $DB_USER -p'$DB_PASS' $DB_NAME > /tmp/$backup_file"
    echo "Backup created in /tmp/$backup_file on remote server."
}

# Sync local changes to remote (using Git or SCP)
deploy_git() {
    echo "--- Deploying via Git ---"
    # This assumes the remote server has the repo and can pull using the GITHUB_PAT
    # We might need to configure the remote git with the PAT first
    local git_cmd="cd $REMOTE_PROJECT_ROOT && git pull origin main"
    remote_exec "$git_cmd"
}

# Help menu
show_help() {
    echo "NGN 2.0.1 Automation Tool"
    echo "Usage: ./bin/deploy.sh [command]"
    echo ""
    echo "Commands:"
    echo "  status     Check remote server and service status"
    echo "  backup     Create a database backup on the remote server"
    echo "  deploy     Pull latest changes on the remote server via Git"
    echo "  finalize   Run migrations and recompute rankings on server"
    echo "  help       Show this help menu"
}

# --- Main ---

case "$1" in
    status)
        check_status
        ;;
    backup)
        backup_db
        ;;
    deploy)
        deploy_git
        ;;
    finalize)
        echo "--- Finalizing Server (Migrations + Rankings) ---"
        remote_exec "cd $REMOTE_PROJECT_ROOT && php scripts/remote-finalize.php"
        ;;
    debug)
        remote_exec "$2"
        ;;
    *)
        show_help
        ;;
esac
