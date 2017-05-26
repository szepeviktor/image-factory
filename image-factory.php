<?php
/*
Plugin Name: Image Factory
Version: 0.2.2
Description: Advanced real-time image optimization with mozjpeg on your server.
Plugin URI: https://github.com/szepeviktor/image-factory
License: The MIT License (MIT)
Author: Viktor Szépe
GitHub Plugin URI: https://github.com/szepeviktor/image-factory
Options: IMAGE_FACTORY_SOCKET

@TODO
- Optimization cron job
    optimize + `wp option delete "$ID" "_optimize"`
- Compile video on Vultr VPS from spinup till WP runs -wp.install
*/

class O1_Image_Factory {

    /**
     * Dimensions of optimized images
     */
    private $sizes = array();

    public function __construct() {

        // Work from the highest quality
        add_filter( 'jpeg_quality', array( $this, 'jpeg_quality' ), 4294967295 );

        // Original file
        add_filter( 'wp_handle_upload', array( $this, 'image_factory_upload' ), 0 );

        // In wp_generate_attachment_metadata()
        add_filter( 'image_make_intermediate_size', array( $this, 'image_factory' ) );
        add_filter( 'wp_generate_attachment_metadata', array( $this, 'metadata' ), 10, 2 );

        // Settings in Options/Media
        add_action( 'admin_init', array( $this, 'settings_init' ) );
    }

    public function jpeg_quality( $quality ) {

        return 100;
    }

    public function image_factory_upload( $upload ) {

        $this->image_factory( $upload['file'], true );

        return $upload;
    }

    public function image_factory( $filename, $original = false ) {

        $sizes_suffix_regexp =  '/-([0-9]+)x([0-9]+)\.[a-zA-Z]+$/';
        $socket_path = $this->get_socket_path();

        if ( ! file_exists( $socket_path ) ) {
            error_log( '[image-factory] Socket does not exist:'
                . $socket_path
            );

            return $filename;
        }
        $factory = stream_socket_client( 'unix://' . $socket_path, $errno, $errstr );

        if ( 0 === $errno ) {
            // Maximum processing time per size
            stream_set_timeout( $factory, 3 );
            // Communicate with server process
            $write = fwrite( $factory, $filename . "\n" );
            if ( false === $write ) {
                error_log( '[image-factory] Socket write error: ' . $filename );
            } else {
                $result = fgets( $factory, 100 );
                $result_string = trim( $result );
                if ( 'OK' === $result_string ) {
                    if ( 1 === preg_match( $sizes_suffix_regexp, $filename, $width_height ) ) {
                        // This image has been optimized.
                        $this->sizes[] = array( $width_height[1], $width_height[2] );
                    } elseif ( ! $original ) {
                        // @TODO Original resized -> no sizes in file name
                        error_log( '[image-factory] Image file name without sizes: '
                            . serialize( $filename )
                        );
                    }
                } else {
                    error_log( '[image-factory] Image processing error/timeout: '
                        . serialize( $result_string )
                    );
                }
            }
            fclose( $factory );
        } else {
            error_log( '[image-factory] Socket open error: ' . $errstr );
        }

        return $filename;
    }

    public function metadata( $metadata, $attachment_id ) {

        if ( isset( $metadata['sizes'] ) ) {
            foreach ( $metadata['sizes'] as $metasize ) {

                $processed = false;
                foreach ( $this->sizes as $processed_size ) {
                    // Only two equals sings ( integer == string )
                    if ( $metasize['width'] == $processed_size[0]
                        && $metasize['height'] == $processed_size[1]
                    ) {
                        // This size is done.
                        $processed = true;
                        break;
                    }
                }

                if ( ! $processed ) {
                    // Record data for image optimization cron job
                    add_post_meta( $attachment_id, '_optimize', $metasize['file'] );
                    error_log( sprintf( '[image-factory] Image missed, ID: %s name: %s',
                        $attachment_id,
                        $metasize['file']
                    ) );
                }
            }
        }

        return $metadata;
    }

    /**
     * Register in Settings API
     *
     * @return void
     */
    public function settings_init() {

        add_settings_section(
            'image_factory_section',
            'Image Factory',
            array( $this, 'admin_section' ),
            'media'
        );
        add_settings_field(
            'image_factory_socket',
            '<label for="image_factory_socket">Socket path</label>',
            array( $this, 'admin_field' ),
            'media',
            'image_factory_section'
        );
        register_setting( 'media', 'image_factory_socket' );
    }

    /**
     * Print the section description for Settings API
     *
     * @return void
     */
    public function admin_section() {

        printf( '<p>Image Factory plugin talks to the background worker through a socket.<p>' );
    }

    /**
     * Print the input field for Settings API
     *
     * @return void
     */
    public function admin_field() {

        $socket_path = esc_attr( $this->get_socket_path() );
        $disabled = defined( 'IMAGE_FACTORY_SOCKET' ) ? ' disabled' : '';

        printf( '<input name="image_factory_socket" id="image_factory_socket" type="text" class="regular-text code" value="%s"%s/>',
            $socket_path,
            $disabled
        );
        printf( '<p class="description">Path to Unix domain socket <code>image-factory-worker.sh</code> listens on.</p>' );
    }

    /**
     * Retrieve socket path.
     *
     * @return @void
     */
    private function get_socket_path() {

        if ( defined( 'IMAGE_FACTORY_SOCKET' ) ) {
            $socket_path = IMAGE_FACTORY_SOCKET;
        } else {
            $socket_path = get_option( 'image_factory_socket' );
        }

        return $socket_path;
    }
}

new O1_Image_Factory();
