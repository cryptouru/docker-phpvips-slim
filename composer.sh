docker run -t \
		-v $PWD/app:/app \
		libvips:php \
		composer install