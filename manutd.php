<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<?php
    /*
     * Shows Manchester United's information
     *
     * Future ideas:
     */
?>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Manchester United's Season Schedule</title>
        <link rel="stylesheet" href="style.css" type="text/css" />
    </head>
    <body>
        <h1>Manchester United's Season Schedule <!--v1.31--></h1>
        <?php
        require("options.php");
        require("functions.php");

        if (!file_exists($cacheFile) || time() - filemtime($cacheFile) > $cacheTime) {
            fetch();
        }

        $reader = new XMLReader();
        $reader->open($cacheFile);
        $printTags = array("MtNo", "DateTime", "Comp", "Opponent", "Venue", "Score");

        // Printing table headers
        echo "<table border='1'>\n";
        echo "            <tr>\n";
        echo "                <th>Sr. No</th>\n";
        echo "                <th>Date/Time</th>\n";
        echo "                <th>Competition</th>\n";
        echo "                <th>Opponent</th>\n";
        echo "                <th>Location</th>\n";
        echo "                <th>Score</th>\n";
        echo "            </tr>\n\n";

        // Start printing table
        $open = false;
        $firstOutput = true;
        $logLastMatchInfo = true;
        $prevMatchInfo = Array();
        $logNextMatchInfo = false;
        $nextMatchInfo = Array();
        $firstNPL = true;
        $stats = Array(
        	'GS' => 0,
        	'GA' => 0,
        	'Win' => 0,
        	'Loss' => 0,
        	'Draw' => 0,
        	'NPL' => 0,
        	'Error' => 0
		);

        while ($reader->read()) {
            if(in_array($reader->name, $printTags)) {
                $reader->read();
                $value = $reader->value;
                $reader->read();

                if ($logLastMatchInfo)
                    array_push($prevMatchInfo, $value);
                if ($logNextMatchInfo)
                    array_push($nextMatchInfo, $value);

                echo "                <td>$value</td>\n";

                if ($reader->name == "Score") {
                    // Collecting statistics
                    $scores = explode(" ",$value);
                    if (isset($scores[0])) {
                    	$stats['GS'] += $scores[0];
					}
					if (isset($scores[2])) {
                    	$stats['GA'] += $scores[2];
					}
                }
            }
            else if ($reader->name == "Match") {
                if (!$open) {
                    if ($firstOutput == false)
                        echo "\n"; // Blame insane source formatting
                    else
                        $firstOutput = false;

                    if ($firstNPL && $logNextMatchInfo) {
                        $firstNPL = false;
                        $logNextMatchInfo = false;
                    }

                    echo "            <tr>\n";

                    // Collecting statistics
                    $result = $reader->getAttribute("Result");
                    if ($result == "W") {
                    	$stats['Win']++;
                    }
                    else if ($result == "L") {
                    	$stats['Loss']++;
                    }
                    else if ($result == "D") {
                    	$stats['Draw']++;
                    }
                    else if ($result == "N") {
                        if ($firstNPL) {
                            $logNextMatchInfo = true;
                            $firstNPL = false;

                            $logLastMatchInfo = false;
                        }
                        $stats['NPL']++;
                    }
                    else {
                    	$stats['Error']++;
                    }

                    if ($logLastMatchInfo) {
                    	$prevMatchInfo = Array();
                    }
                }
                else {
                	echo "            </tr>\n";
                }

                $open = !$open;
            }
            else if ($reader->name == "Fixtures") {
            	$lastRefresh = $reader->getAttribute("LastRefresh");
            }
        }

        echo "        </table>\n\n";
        $reader->close();

        // Printing extra statistics if requested
        if (isset($_GET['show'])) {
	        if ($_GET['show'] == "stats" || $_GET['show'] == "all") {
	            echo "        <h2>Stats</h2>\n";
	            if ($stats['Error'] == null) {
	                $total = $stats['Win'] + $stats['Loss'] + $stats['Draw'];
	                $stats['WinP'] = round($stats['Win'] * 100 / $total, 2);
	                $stats['LossP'] = round($stats['Loss'] * 100 / $total, 2);
	                $stats['DrawP'] = round($stats['Draw'] * 100 / $total, 2);

	                echo "        <b>Wins:</b> ".$stats['Win']." <i>(".$stats['WinP']."%)</i><br />\n";
	                echo "        <b>Losses</b>: ".$stats['Loss']." <i>(".$stats['LossP']."%)</i><br />\n";
	                echo "        <b>Draws:</b> ".$stats['Draw']." <i>(".$stats['DrawP']."%)</i><br />\n";
	                echo "        <b>Not Played Yet:</b> ".$stats['NPL']."<br />\n";
	                echo "        <b>Goals Difference:</b> ".($stats['GS']>$stats['GA']?"+":($stats['GS']<$stats['GA']?"-":"")).($stats['GS']-$stats['GA'])." <i>(".$stats['GS']." - ".$stats['GA'].")</i>\n\n";
	            }
	            else
	                echo "        Got ".$stats['Error']." errors while collecting stats.";
	        }

	        if ($_GET['show'] == "prev" || $_GET['show'] == "all") {
	            echo "        <h2>Prev Match</h2>\n";
	            echo "        <b>Match No:</b> ".$prevMatchInfo[0]."<br />\n";
	            echo "        <b>Date/Time:</b> ".$prevMatchInfo[1]."<br />\n";
	            echo "        <b>Competition:</b> ".$prevMatchInfo[2]."<br />\n";
	            echo "        <b>Opponents:</b> ".$prevMatchInfo[3]."<br />\n";
	            echo "        <b>Location:</b> ".$prevMatchInfo[4]."<br />\n";
	            echo "        <b>Result:</b> ".$prevMatchInfo[5]."<br />\n";
	        }

	        if ($_GET['show'] == "next" || $_GET['show'] == "all") {
	            echo "        <h2>Next Match</h2>\n";
	            echo "        <b>Match No:</b> ".$nextMatchInfo[0]."<br />\n";
	            echo "        <b>Date/Time:</b> ".$nextMatchInfo[1]."<br />\n";
	            echo "        <b>Competition:</b> ".$nextMatchInfo[2]."<br />\n";
	            echo "        <b>Opponents:</b> ".$nextMatchInfo[3]."<br />\n";
	            echo "        <b>Location:</b> ".$nextMatchInfo[4]."<br />\n";
	        }

	        if ($_GET['show'] == "debug" || $_GET['show'] == "all") {
	            echo "        <h2>Info</h2>\n";
	            echo "        <b>Last Refresh:</b> ".date("d-M-Y H:i:s", $lastRefresh)." (".relativeTime($lastRefresh).")<br />\n";
	            echo "        <b>Cache Time:</b> ".readableTime($cacheTime)."<br />\n";
	            echo "        <b>Cache Size:</b> ".(filesize($cacheFile)/1024)." KB\n";
	        }

		if ($_GET['show'] == "stats" || $_GET['show'] == "all") {
	    	    echo "	  <br /><br />Information Source: <a href=\"$baseUrl\" target=\"_blank\">Manchester United's schedule page</a>";
		}
        }

        // Adding SMS support for next match notification
        ?>
    </body>
</html>
