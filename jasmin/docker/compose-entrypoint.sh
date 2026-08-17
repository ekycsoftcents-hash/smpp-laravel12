#!/bin/bash
set -e

CONFIG_FILE="${CONFIG_PATH}/jasmin.cfg"

# Activate the source template's commented defaults for container networking.
sed -i -E \
  -e "/\[redis-client\]/,/^\[/ s/^#(host[[:space:]]*=).*/\1 ${REDIS_CLIENT_HOST:-redis}/" \
  -e "/\[redis-client\]/,/^\[/ s/^#(port[[:space:]]*=).*/\1 ${REDIS_CLIENT_PORT:-6379}/" \
  -e "/\[amqp-broker\]/,/^\[/ s/^#(host[[:space:]]*=).*/\1 ${AMQP_BROKER_HOST:-rabbit-mq}/" \
  -e "/\[amqp-broker\]/,/^\[/ s/^#(port[[:space:]]*=).*/\1 ${AMQP_BROKER_PORT:-5672}/" \
  -e "/\[amqp-broker\]/,/^\[/ s/^#(username[[:space:]]*=).*/\1 ${AMQP_BROKER_USERNAME:-jasmin}/" \
  -e "/\[amqp-broker\]/,/^\[/ s/^#(password[[:space:]]*=).*/\1 ${AMQP_BROKER_PASSWORD:-jasmin_secret}/" \
  "$CONFIG_FILE"

exec /jasmin-upstream-entrypoint.sh "$@"
