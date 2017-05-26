# Image Factory

Advanced real-time image optimization with mozjpeg on your server.

This project uses [JPEG Archive](https://github.com/danielgtaylor/jpeg-archive) by Daniel G. Taylor
which depends on [mozjpeg](https://github.com/mozilla/mozjpeg), an improved JPEG encoder.

### Install dependencies and background worker

```bash
apt-key adv --keyserver pgp.mit.edu --recv-keys 451A4FBA
echo "deb http://szepeviktor.github.io/debian/ jessie main" > /etc/apt/sources.list.d/szepeviktor.list
apt-get update && apt-get install -y socat optipng jpeginfo jpeg-archive
cp -a ./bin/image-factory-worker.sh /usr/local/bin/
```

### Install init script and options

Source: https://github.com/asaif/socat-init

Replace `www-data` (in 3 places) with the PHP user.

```bash
cp -a ./init.d/socat /etc/init.d/
cat > /etc/default/socat.conf <<<EOF
SOCAT_DEFAULTS="-d -d -d -ly"
OPTIONS="UNIX-LISTEN:/var/www/above-document-root/factory.sock,mode=600,fork,user=www-data,group=www-data EXEC:/usr/local/bin/image-factory-worker.sh,pipes,su=www-data"
EOF
update-rc.d socat defaults
```

Optional second instance.

```bash
cp -a ./init.d/socat /etc/init.d/socat02
sed -i 's;/etc/default/socat\.conf;/etc/default/socat02.conf;' /etc/init.d/socat02
cat > /etc/default/socat02.conf <<<EOF
SOCAT_DEFAULTS="-d -d -d -ly"
OPTIONS="UNIX-LISTEN:/var/www/other-document-root/factory.sock,mode=600,fork,user=www-data,group=www-data EXEC:/usr/local/bin/image-factory-worker.sh,pipes,su=www-data"
EOF
update-rc.d socat02 defaults
```

### Setting Socket path

The Socket path option is on the Settings / Media page.

Or you could add a define to your `wp-config.php`:

```php
define( 'IMAGE_FACTORY_SOCKET', '/var/www/site/factory.sock' );
```

### Manual test

```bash
wp option add "image_factory_socket" "/var/www/above-document-root/factory.sock" --autoload=no
/usr/bin/socat UNIX-LISTEN:/var/www/above-document-root/factory.sock,mode=600,fork,user=www-data,group=www-data EXEC:/usr/local/bin/image-factory-worker.sh,pipes,su=www-data
```

Now upload a JPEG image to Media Library and watch syslog:

`tail -f /var/log/syslog`

### TODO

Support systemd.
