<?php

/**
 * @author Benedikt Bauer
 *
 *
 */

class Portal_model extends CI_Model {

    public function __construct() {
        parent::__construct ();
        $this->load->helper('array');
    }

    /**
     * Get database connection details for portal user
     * @param unknown_type $user
     * @throws Exception
     */
    public function getConnectionForUser($user) {
        $DBLocal = $this->load->database(ENVIRONMENT, TRUE);

        $columns = array('id', 'user', 'username', 'password', 'database', 'hostname');
        $DBLocal->select($columns)->from('portals')->where('user', $user);
        $query = $DBLocal->get();

        if ($query->num_rows() != 1) {
            $DBLocal->close();
            throw new Exception("Unknown User");
        } else {
            $DBLocal->close();
            return $query->row_array();
        }
    }

    /**
     * Get all Songs from Playlist
     */
    public function getPlaylist($connection, $index, $offset) {
        $DBRemote = $this->load->database($connection, TRUE);

        $columns = array('ID', 'artist', 'title', 'album', 'picture');
        $DBRemote->select($columns)->from('songlist')->where('songtype', 'S')->limit(20, $offset);

        if ($index != null) {
            $DBRemote->where("artist LIKE '".$index."%'");
        }

        $query = $DBRemote->get();

        $result['songs'] = $query->result();

        $DBRemote->select()->from('songlist')->where('songtype', 'S');

        if ($index != null) {
            $DBRemote->where("artist LIKE '".$index."%'");
        }

        $result['count'] = $DBRemote->count_all_results();

        return $result;
    }

}

/* End of file portal_model.php */
/* Location: ./application/models/portal_model.php */