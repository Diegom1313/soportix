#!/bin/bash
set -euo pipefail
apt-get update
apt-get install -y ca-certificates curl unattended-upgrades
curl -fsSL https://get.docker.com | sh
usermod -aG docker ubuntu
