#!/usr/bin/env bash
# systemd service configuration for NEXVARY CX Cloud

set -euo pipefail

# ── Queue worker service ──────────────────────────────────────────────────────

nx_install_queue_worker() {
  nx_step "Installing Laravel queue worker service"

  cat > /etc/systemd/system/nexvary-queue.service <<UNIT
[Unit]
Description=NEXVARY CX Cloud — Queue Worker
After=network.target postgresql.service redis.service
Wants=postgresql.service redis.service

[Service]
Type=simple
User=${NX_APP_USER:-nexvary}
Group=${NX_APP_USER:-nexvary}
WorkingDirectory=${NX_APP_DIR:-/var/www/nexvary-cx}
ExecStart=/usr/bin/php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --memory=256
ExecReload=/bin/kill -HUP \$MAINPID
KillMode=mixed
Restart=always
RestartSec=5

# Hardening
NoNewPrivileges=yes
PrivateTmp=yes
ProtectSystem=strict
ReadWritePaths=${NX_APP_DIR:-/var/www/nexvary-cx}/storage

[Install]
WantedBy=multi-user.target
UNIT

  systemctl daemon-reload
  systemctl enable nexvary-queue --now
  nx_ok "Queue worker service installed and started"
}

# ── Scheduler (cron replacement) ─────────────────────────────────────────────

nx_install_scheduler() {
  nx_step "Installing Laravel scheduler service"

  cat > /etc/systemd/system/nexvary-scheduler.service <<UNIT
[Unit]
Description=NEXVARY CX Cloud — Task Scheduler (single run)
After=network.target

[Service]
Type=oneshot
User=${NX_APP_USER:-nexvary}
Group=${NX_APP_USER:-nexvary}
WorkingDirectory=${NX_APP_DIR:-/var/www/nexvary-cx}
ExecStart=/usr/bin/php artisan schedule:run --no-interaction
UNIT

  cat > /etc/systemd/system/nexvary-scheduler.timer <<UNIT
[Unit]
Description=NEXVARY CX Cloud — Task Scheduler (every minute)
After=network.target

[Timer]
OnActiveSec=60
OnUnitActiveSec=60
AccuracySec=1s
Unit=nexvary-scheduler.service

[Install]
WantedBy=timers.target
UNIT

  systemctl daemon-reload
  systemctl enable nexvary-scheduler.timer --now
  nx_ok "Scheduler timer installed and started"
}

# ── Verify all required services ──────────────────────────────────────────────

nx_verify_services() {
  nx_step "Verifying service status"

  local all_ok=true

  local services=('nginx' 'postgresql' 'postgresql-16' 'redis-server' 'redis')

  # Find the actual postgresql and redis service names
  local pg_svc; pg_svc="$(systemctl list-units 'postgresql*' --no-legend 2>/dev/null | awk '$4=="running"{print $1}' | head -1)"
  local redis_svc; redis_svc="$(systemctl list-units 'redis*' --no-legend 2>/dev/null | awk '$4=="running"{print $1}' | head -1)"

  for svc_candidate in nginx "$pg_svc" "$redis_svc" nexvary-queue nexvary-scheduler.timer; do
    [[ -z "$svc_candidate" ]] && continue
    if systemctl is-active --quiet "$svc_candidate" 2>/dev/null; then
      nx_ok "$svc_candidate is active"
    else
      nx_warn "$svc_candidate is not active"
      all_ok=false
    fi
  done

  if [[ "$all_ok" == 'false' ]]; then
    nx_warn "Some services are not running. Use 'systemctl status <service>' to investigate."
  fi
}

# ── Print startup URL ─────────────────────────────────────────────────────────

nx_print_setup_url() {
  local domain="${NX_DOMAIN:-localhost}"
  local scheme='http'
  if systemctl is-active --quiet nginx 2>/dev/null && \
     nginx -T 2>/dev/null | grep -q 'ssl_certificate'; then
    scheme='https'
  fi

  local setup_url="${scheme}://${domain}/setup"
  local setup_token=''
  if [[ -f /etc/nexvary/setup_token ]]; then
    setup_token="$(cat /etc/nexvary/setup_token)"
  fi

  echo ""
  printf "${C_GREEN}${C_BOLD}╔══════════════════════════════════════════════════════╗${C_RESET}\n"
  printf "${C_GREEN}${C_BOLD}║      NEXVARY CX Cloud — Installation Complete!       ║${C_RESET}\n"
  printf "${C_GREEN}${C_BOLD}╚══════════════════════════════════════════════════════╝${C_RESET}\n"
  echo ""
  printf "${C_BOLD}  Open your browser and go to:${C_RESET}\n"
  printf "  ${C_GREEN}${C_BOLD}%s${C_RESET}\n" "$setup_url"
  echo ""
  if [[ -n "$setup_token" ]]; then
    printf "${C_BOLD}  Setup token (required on first visit):${C_RESET}\n"
    printf "  ${C_YELLOW}%s${C_RESET}\n" "$setup_token"
    printf "${C_DIM}  (Also saved at /etc/nexvary/setup_token)${C_RESET}\n"
    echo ""
  fi
  printf "${C_DIM}  Logs:    ${NX_APP_DIR:-/var/www/nexvary-cx}/storage/logs/laravel.log${C_RESET}\n"
  printf "${C_DIM}  Docs:    https://nexvary.com/docs${C_RESET}\n"
  printf "${C_DIM}  GitHub:  https://github.com/nexvary/NEXVARY-CX-Cloud${C_RESET}\n"
  echo ""
}
