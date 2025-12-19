<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Zenith_Mobile_Auth_Updater {

    private $plugin_slug;
    private $version;
    private $cache_key;
    private $cache_allowed;
    private $github_owner;
    private $github_repo;

    public function __construct( $plugin_file, $github_owner, $github_repo ) {
        $this->plugin_slug   = plugin_basename( $plugin_file );
        $this->version       = ZMA_VERSION;
        $this->cache_key     = 'zma_github_updater';
        $this->cache_allowed = false; // Set true for production to cache GitHub responses for 12 hours
        $this->github_owner  = $github_owner;
        $this->github_repo   = $github_repo;

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
        add_filter( 'plugins_api', [ $this, 'check_info' ], 10, 3 );
    }

    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $remote = $this->request();

        if ( $remote && version_compare( $this->version, $remote->version, '<' ) ) {
            $res = new stdClass();
            $res->slug = $this->plugin_slug;
            $res->plugin = $this->plugin_slug;
            $res->new_version = $remote->version;
            $res->url = $remote->url;
            $res->package = $remote->download_url;
            
            $transient->response[ $this->plugin_slug ] = $res;
        }

        return $transient;
    }

    public function check_info( $false, $action, $arg ) {
        if ( isset( $arg->slug ) && $arg->slug === dirname( $this->plugin_slug ) ) {
            $remote = $this->request();
            if ( $remote ) {
                $res = new stdClass();
                $res->name = $remote->name;
                $res->slug = dirname( $this->plugin_slug );
                $res->version = $remote->version;
                $res->tested = '6.7'; // Update manually or fetch if needed
                $res->requires = '5.0';
                $res->author = 'Mahdi Soltani';
                $res->author_profile = 'https://github.com/' . $this->github_owner;
                $res->download_link = $remote->download_url;
                $res->trunk = $remote->download_url;
                $res->last_updated = $remote->last_updated;
                $res->sections = [
                    'description' => $remote->sections['description'],
                    'changelog'   => $remote->sections['changelog'],
                ];
                return $res;
            }
        }
        return $false;
    }

    private function request() {
        $remote = get_transient( $this->cache_key );

        if( false === $remote || ! $this->cache_allowed ) {
            $remote = $this->fetch_github_data();
            if( $remote ) {
                set_transient( $this->cache_key, $remote, 12 * HOUR_IN_SECONDS );
            }
        }

        return $remote;
    }

    private function fetch_github_data() {
        // Fetch Latest Release
        $url = "https://api.github.com/repos/{$this->github_owner}/{$this->github_repo}/releases/latest";
        $response = wp_remote_get( $url );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ) );

        $remote = new stdClass();
        $remote->name = $body->name;
        $remote->version = str_replace( 'v', '', $body->tag_name ); // Strip 'v' from v1.0.0
        $remote->url = $body->html_url;
        $remote->download_url = $body->zipball_url;
        $remote->last_updated = $body->published_at;
        $remote->sections = [
            'description' => 'Latest release from GitHub',
            'changelog'   => $body->body // Release notes
        ];

        return $remote;
    }
}
