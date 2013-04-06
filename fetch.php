<?php
    /*
     * This file is responsible for forcing cache refreshes
     * This will mainly be used by a cronjob or a client app
     *
     * Future ideas:
	 * +>> Make this file write the XML out (either cached or refreshed)
     * +>> Add Passkey for security
     */
    require("functions.php");

    fetch();
?>
