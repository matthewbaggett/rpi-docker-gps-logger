FROM gone/php-arm:cli
COPY .docker/service /etc/service
RUN chmod +x /etc/service/*/run \
 && chmod +x /app/push-to-redis.php
