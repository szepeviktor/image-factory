# Image Factory

Advanced real-time image optimization with mozjpeg on your server.

### Add Debian repo key

`apt-key adv --keyserver pgp.mit.edu --recv-keys 451A4FBA`

### Add repo

`echo "deb http://szepeviktor.github.io/debian/ jessie main" > /etc/apt/sources.list.d/szepeviktor.list`

### Install dependencies

`apt-get install -y socat optipng jpeginfo jpeg-archive`

### Install background worker

`cp -a ./bin/image-factory-worker.sh /usr/local/bin/`

### Install init script and options

Source: https://github.com/asaif/socat-init

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
sed -i 's;/etc/default/socat\.conf;/etc/default/socat02\.conf;' /etc/init.d/socat02
cat > /etc/default/socat02.conf <<<EOF
SOCAT_DEFAULTS="-d -d -d -ly"
OPTIONS="UNIX-LISTEN:/var/www/other-document-root/factory.sock,mode=600,fork,user=www-data,group=www-data EXEC:/usr/local/bin/image-factory-worker.sh,pipes,su=www-data"
EOF
update-rc.d socat02 defaults
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
