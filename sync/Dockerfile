FROM gone/php-arm:cli
RUN apt-get -qq update && \
    apt-get -yq install --no-install-recommends \
        build-essential \
        php-dev \
        php-redis \
        libhiredis-dev && \
    cd /tmp && \
    git clone https://github.com/nrk/phpiredis.git && \
    cd phpiredis && \
    phpize && \
    ./configure --enable-phpiredis && \
    make && \
    make install && \
    echo "extension=phpiredis.so" > /etc/php/7.0/cli/php.ini && \
    cd - && \
    apt remove -y build-essential && \
    apt-get autoremove -y && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY .docker/service /etc/service
RUN chmod +x /etc/service/*/run \
 && chmod +x /app/sync.php

RUN php -m | grep -i "redis"