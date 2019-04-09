#+++++++++++++++++++++++++++++++++++++++
# Dockerfile for webdevops/php-nginx-dev:7.3
# https://github.com/webdevops/Dockerfile/blob/master/docker/php-nginx-dev/7.3
# https://dockerfile.readthedocs.io/en/latest/content/DockerImages/dockerfiles/php-nginx-dev.html
#    -- automatically generated  --
#+++++++++++++++++++++++++++++++++++++++

FROM webdevops/php-nginx:7.3
# https://github.com/webdevops/Dockerfile/blob/master/docker/php-nginx/7.3

ENV WEB_DOCUMENT_ROOT=/app/www \
  WEB_DOCUMENT_INDEX=index.php \
  WEB_ALIAS_DOMAIN=*.vm \
  WEB_PHP_TIMEOUT=600 \
  WEB_PHP_SOCKET=""
ENV WEB_PHP_SOCKET=127.0.0.1:9000
ENV WEB_NO_CACHE_PATTERN="\.(css|js|gif|png|jpg|svg|json|xml)$"

COPY conf/ /opt/docker/

RUN set -x \
  # Install development environment
  && wget -q -O - https://packages.blackfire.io/gpg.key | apt-key add - \
  && echo "deb https://packages.blackfire.io/debian any main" | tee /etc/apt/sources.list.d/blackfire.list \
  && apt-install \
  blackfire-php \
  blackfire-agent \
  && pecl install xdebug-2.7.0 \
  && echo "zend_extension=xdebug.so" > /usr/local/etc/php/conf.d/xdebug.ini \
  # Enable php development services
  && docker-service enable syslog \
  && docker-service enable postfix \
  && docker-service enable ssh \
  && docker-run-bootstrap \
  && docker-image-cleanup

#+++++++++++++++++++++++++++++++++++++++++++++++++
#   -- Libvips & image processing libs setup  --
#+++++++++++++++++++++++++++++++++++++++++++++++++

# essential stuff to build
RUN \
  apt-get update && \
  apt-get install -y \
  build-essential \
  unzip \
  wget \
  pkg-config

# stuff we need to build our own libvips
RUN \
  apt-get install -y \
  glib-2.0-dev \
  libexpat-dev \
  librsvg2-dev \
  libpng-dev \
  libgif-dev \
  libjpeg-dev \
  libexif-dev \
  liblcms2-dev \
  gir1.2-pango-1.0 libpango1.0-dev libpangocairo-1.0-0 libpangoxft-1.0-0 \
  gcc libmagickwand-dev

RUN pecl install imagick

# enable the imagick.so extension
RUN \
  echo "extension=imagick.so" > /usr/local/etc/php/conf.d/imagick.ini && \
  ln -s /usr/local/etc/php/conf.d/imagick.ini

# build in /build, install to /usr
WORKDIR /build
COPY install-vips.sh /build
RUN \
  sh ./install-vips.sh 8 7 4

# install the php extension that links it to libvips
RUN \
  pecl install vips

# enable the vips.so extension
RUN \
  echo "extension=vips.so" > /usr/local/etc/php/conf.d/vips.ini && \
  ln -s /usr/local/etc/php/conf.d/vips.ini

WORKDIR /app

COPY app/ /app