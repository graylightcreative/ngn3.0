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

# Load environment variables
if [ -f .env ]; then
    export $(grep -v '^#' .env | xargs)
else
    echo "Error: .env file not found."
    exit 1
fi

# Configuration
SSH_USER=$(echo $SSH_CONNECTION_STRING | cut -d'@' -f1)
SSH_HOST=$(echo $SSH_CONNECTION_STRING | cut -d'@' -f2)
REMOTE_PROJECT_ROOT="/www/wwwroot/beta.nextgennoise.com"

# --- Functions ---

# Execute a command on the remote server via SSH
remote_exec() {
    local cmd=$1
    echo "Executing remote command: $cmd"
    if command -v sshpass >/dev/null 2>&1 && [ ! -z "$SSH_PASSWORD" ]; then
        sshpass -p "$SSH_PASSWORD" ssh -o StrictHostKeyChecking=no "$SSH_CONNECTION_STRING" "$cmd"
    else
        ssh -o StrictHostKeyChecking=no "$SSH_CONNECTION_STRING" "$cmd"
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
    *)
        show_help
        ;;
esac
