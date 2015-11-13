#!/bin/bash
#
# Optimize images for Image Factory plugin.
#
# VERSION       :0.2.0
# DATE          :2015-11-13
# AUTHOR        :Viktor Szépe <viktor@szepe.net>
# LICENSE       :The MIT License (MIT)
# URL           :https://github.com/szepeviktor/image-factory
# BASH-VERSION  :4.2+
# DEPENDS       :apt-get install jpeginfo jpeg-archive optipng
# LOCATION      :/usr/local/bin/image-factory-worker.sh

JPEG_RECOMPRESS="/usr/bin/jpeg-recompress --target 0.9995 --accurate --strip"
LOGGER_TAG="$(basename --suffix=.sh "$0")"

Handle_error() {
    local MSG
    local RET="$1"
    local ITEM="$2"

    case "$RET" in
        1)
            MSG="Invalid JPEG image"
            ;;
        2)
            MSG="jpeg-recompress failure"
            ;;
        3)
            MSG="Failed move back optimized image"
            ;;
        4)
            MSG="Invalid image after jpeg-recompress"
            ;;
        10)
            MSG="optipng failure"
            ;;
        20)
            MSG="Empty image path"
            ;;
        21)
            MSG="Missing image"
            ;;
        *)
            MSG="Unknown error ${RET}"
            ;;
    esac
    echo "${MSG} (${ITEM})" >&2
}

Optimize_image() {
    local IMG="$1"
    local TMPIMG

    [ -z "$IMG" ] && return 20
    [ -f "$IMG" ] || return 21

    # JPEG
    if [ "$IMG" != "${IMG%.jpg}" ] || [ "$IMG" != "${IMG%.jpeg}" ] \
        || [ "$IMG" != "${IMG%.JPG}" ] || [ "$IMG" != "${IMG%.JPEG}" ]; then
        logger -t "$LOGGER_TAG" "JPEG:${IMG}"
        jpeginfo --check "$IMG" &> /dev/null || return 1
        TMPIMG="$(tempfile)"
        if ! nice ${JPEG_RECOMPRESS} --quiet "$IMG" "$TMPIMG" &> /dev/null; then
            rm -f "$TMPIMG" &> /dev/null
            return 2
        fi
        if [ -f "$TMPIMG" ] && ! mv -f "$TMPIMG" "$IMG"; then
            rm -f "$TMPIMG"
            return 3
        fi
        jpeginfo --check "$IMG" &> /dev/null || return 4
    fi

    # PNG
    if [ "$IMG" != "${IMG%.png}" ] \
        || [ "$IMG" != "${IMG%.PNG}" ]; then
        logger -t "$LOGGER_TAG" "PNG:${IMG}"
        nice optipng -quiet -preserve -clobber -strip all -o7 "$IMG" &> /dev/null || return 10
    fi

    # Optimized OK or other type of image.
    return 0
}

# Allow specifying a file to optimize
if [ -t 1 ] && [ -r "$1" ]; then
    ATTACHMENT="$1"
else
    read -r ATTACHMENT
fi

if Optimize_image "$ATTACHMENT"; then
    echo "OK"
else
    Handle_error $? "$ATTACHMENT"
fi

exit 0
