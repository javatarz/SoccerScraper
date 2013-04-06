<?php
    /*
     * This file is holds all functions
     *
     * Currently supported teams are:
     * Manchester United
     *
     * Future ideas:
     * +>> Backup XML before writing
     * +>> If write error, restore backup
     * +>> Implement passkey checks for fetch()
     * +>> Add SMS support
     * +>> Remote access application
     * +>> Light cache checking
     * +>> DST Timezones
     * +>> Export to CSV
     * +>> Add to outlook?
     *
     * TODO: Externalize all RegExps
     */

function fetch() {
    require("options.php");

    // Starting xml writing
    @date_default_timezone_set($defaultTimeZone);
    $writer = new XMLWriter();

    // Writing XML header
    $writer->openURI($cacheFile);
    $writer->startDocument("1.0");
    $writer->setIndent(8);

    $writer->startElement("Fixtures");
    $writer->writeAttribute("LastRefresh", time());
    $writer->startElement("ManUtd");

    // Setting up all regular expressions
    // Pattern to find if table exists
    $tablePattern = '/<table[\S \d a-z = "]*?>[\W]*?<thead>[\W]*?<tr>[\W]*?<th[\S \d a-z = "]*?>[\W]*?[a-z A-Z]*?[\d]*?[\W]*?<\/th>[\W]*?<\/tr>[\W]*?<\/thead>[\W]*?<tbody>(.*?)<\/tbody><\/table>/is';
    $tablePattern = '/<table[\S \d a-z = "]*?>[\W]*?<thead>.*?<\/thead>(.*?)<\/table>/is';
    // Pattern to get total count of pages in schedule
    $pageNumberPattern = '/Page [0-9]* of ([0-9])*/is';
    // Pattern to get each game
    $gamePattern = '/<tr[\S a-z = "]*?>(.*?)<\/tr>/is';
    // Pattern to get information on games
    //$gameInformationPattern = '/<td class="(.*?)">(.*?)<\/td>/is';
    $gameInformationPattern = '/<td(?: class="(.*?)"|)>(.*?)<\/td>/is';
    // Pattern to get scores
    $scorePattern = '/([\d])* - ([\d])*/';
    $monthPattern = '/[a-zA-Z]{3}/';
    $datePattern = '/[\d]{2}/';
    $timePattern = '/([\d]{2}:[\d]{2})/';
    $timeZonePattern = '/<span style="display: none;">(.*?)<\/span>/is';

    // Fetching Manchester United's Schedule
    /*  Is this the first part of the year or the second?
     *  Accordingly, lets set the year variables. */
    if (yearPart(date("M"))) {
        $first_half = date("Y")-1;
        $second_half = date("Y");
    }
    else {
        $first_half = date("Y");
        $second_half = date("Y")+1;
    }


    // Results are multipage so lets loop it
    for ($pgNo = 1, $page_count=1, $count = 0; $pgNo <= $page_count; $pgNo++) {
        // Loading the base URL for the fixtures
        $url = $baseUrl."?pageNo=".$pgNo;
		// Fetching the page
        $html = file_get_contents($url);

        // Lets check if we can find the table
        if(preg_match_all($tablePattern, $html, $tableContentMatches)) {
            // Is this the first page?
            if($pgNo == 1) { // Lets find some initialization values
                if (preg_match($pageNumberPattern, $html, $pageCountMatches)) {
                    $page_count = $pageCountMatches[1]; // Total number of pages
                }
            }

            // Lets start finding matches
            if(preg_match_all($gamePattern, $tableContentMatches[1][0], $gameMatches)) {
                foreach ($gameMatches[1] as $unparsedMatchesData) {
                    // Fetching information from inside these matches
                    if (preg_match_all($gameInformationPattern, $unparsedMatchesData, $gameInformationMatches)) {
                        //var_dump($gameInformationMatches);
                        $gameInformationMatchesCount = count($gameInformationMatches[0]);
                        for ($i = 0; $i < $gameInformationMatchesCount; $i++) {
                            $key = $gameInformationMatches[1][$i];
                            if ($i == 3) { // Venue details don't have a td class
                                $key = "venue";
                            }
                            else if ("" == trim($key)) {
                                $key = "kickoff";
                            }

                            $parsedMatchInformation[$key] = trim($gameInformationMatches[2][$i]);
                        }

                        // Starting off match details
                        $writer->startElement("Match");

                        $cleanScore = cleanText($parsedMatchInformation['score']);
                        // Writing result attribute first
                        if ("" != $cleanScore) {
                            preg_match($scorePattern, $parsedMatchInformation['score'], $score);
                            if ($score[1] > $score[2]) {
                                $result = "W";
                            }
                            else if ($score[1] < $score[2]) {
                                $result = "L";
                            }
                            else {
                                $result = "D";
                            }
                        }
                        else {
                            $result = "N";
                        }
                        $writer->writeAttribute('Result', $result);

                        // Writing match number
                        $writer->writeElement('MtNo', ++$count);

                        // Looking for the Month and Date of the match
                        preg_match($monthPattern, $parsedMatchInformation['date'], $month);
                        preg_match($datePattern, $parsedMatchInformation['date'], $date);
                        // Finding year of the match
                        if (!timeNotDecided($month[0])) {
	                        if (yearPart($month[0])) {
	                            $target_year = $second_half;
	                        }
	                        else {
	                            $target_year = $first_half;
	                        }
						}

                        // Finding the exact kickoff time
                        preg_match($timePattern, $parsedMatchInformation['kickoff'], $timeMatch);
                        preg_match($timeZonePattern, $parsedMatchInformation['kickoff'], $timeZoneMatch);

                        //Compiling single variable for time
                        if (!timeNotDecided($month[0])) {
                        	if (!isset($timeMatch[0])) {
                        		$timeMatch[0] = "";
                        	}

	                        $match_time_val = strtotime("$date[0] $month[0] $target_year $timeMatch[0]") + getTimeZoneOffset(trim($timeZoneMatch[1]));
	                        $match_time = date("d M Y <\i>H:i</\i>", $match_time_val);
	                        // Is time not decided yet?
	                        if ("" == $timeMatch[0]) {
	                            $match_time = str_replace(date("H:i", getTimeZoneOffset(trim($timeZoneMatch[1])) - $defaultTimeZoneOffset), "TBA", $match_time);
	                        }
						} else {
							$match_time = "TBA";
						}
                        // Writing match time
                        $writer->writeElement('DateTime',$match_time);

                        // Print match data
                        $writer->writeElement('Comp', $parsedMatchInformation['competition']);
                        $writer->writeElement('Opponent', $parsedMatchInformation['opponent']);
                        $writer->writeElement('Venue', cleanText($parsedMatchInformation['venue']));

                        if ("" == $cleanScore) {
                            $writer->writeElement('Score', 'NPL');
                            if (isset($next_match)) {
                                $next_match = array ($count-1, $match_time, $parsedMatchInformation['competition'], $parsedMatchInformation['opponent'], cleanText($parsedMatchInformation['venue']));
                            }
                        }
                        else {
                            $writer->writeElement('Score', $cleanScore);
                        }

                        // Close that table row, darn it!
                        $writer->endElement();
                    }
                }
            }

            // Was this the last page? Close table if it is
            if ($i == $page_count) {
                $writer->endElement(); // End manutd
            }
        }
        // Did the grandfather regexp fail? Don't just sit there, report an error!
        else {
            echo "There is probably a change in the <a href=\"http://www.manutd.com/en/Fixtures-And-Results/United-Fixtures-And-Results.aspx\">official Manchester United fixtures page</a>. Please contact me[at]karunab[dot]com and inform me about this. Thanks!";
        }
    }

    // End rss
    $writer->endElement();
    // End document
    $writer->endDocument();
    // Clear flush
    $writer->flush();
}

function yearPart($month) {
    switch ($month) {
        case "Jan":
        case 'Feb':
        case 'Mar':
        case 'Apr':
        case 'May': return true; // Second half of the season
        case 'Jun':
        case 'Jul':
        case 'Aug':
        case 'Sep':
        case 'Oct':
        case 'Nov':
        case 'Dec': return false; // First half of the season
        default: die("ERROR! Invalid Month Format. Given month was \"$month\"");
    }
}

function timeNormalize($time) {
    if (timeNotDecided($time))
        return "00:00";
    else
        return $time;
}

function timeNotDecided($time) {
    return in_array($time, array("TBC", "TBD", "TBA"));
}

function getTimeZoneOffset($timeZone) {
    if ("BST" == $timeZone) {
        return 16200;
    }

    return 19800;
}

function cleanText($text) {
    return trim(strip_tags($text));
}

function clean_Array($array) {
    foreach ($array as $val) {
        if (!empty($val) || !isset($val) || $val != '') {
            $clean[] = $val;
        }
    }
    return $clean;
}

function readableTime($diff) {
    if ($diff < 0) {
	return "0 s";
    }

    if ($diff >= 3600) {
        $hrs = floor($diff / 3600);
        $diff = $diff % 3600;
    }

    if ($diff >= 60) {
        $mins = floor($diff / 60);
        $diff = $diff % 60;
    }

    if ($diff >= 1)
        $secs = floor($diff);

	$readTime = "";
    if (isset($hrs)) {
    	$readTime = $hrs."h ";
    }
    if (isset($mins)) {
    	$readTime .= $mins."m ";
	}
    if (isset($secs)) {
    	$readTime .= $secs."s";
    } else {
	$readTime = trim($readTime);
    }

    return $readTime;
}

function relativeTime($oldTime) {
    return readableTime(time()-$oldTime)." ago";
}

function isEventClose($eventTime) {
    require("options.php");

    $now = time();
    $diff = strtotime($eventTime) - $now;

    echo date("d M Y H:i",$now)."<br />";
    echo $eventTime."<br />";
    echo readableTime($diff)."<br />";

    if ($diff > 0 && $diff <= $remindTime)
        return true;
    return false;
}

function alertForEvent() {
    $nextMatchInfo = Array();
    array_push($nextMatchInfo,"57");
    array_push($nextMatchInfo,"03 Apr 2010 18:15");
    array_push($nextMatchInfo,"Barclays Premier League");
    array_push($nextMatchInfo,"Chelsea");
    array_push($nextMatchInfo,"H");

    switch($nextMatchInfo[2]) {
        case "Barclays Premier League": $competition = "BPL"; break;
        case "UEFA Champions League": $competition = "UCL"; break;
        case "FA Cup": $competition = "FA"; break;
        case "Community Shield": $competition = "CommSh"; break;
        case "Pre-season": $competition = "Pre"; break;
        case "League Cup": $competition = "LC"; break;
        default: $competition = $nextMatchInfo[2];
    }

    $to = "9869469862.JAndert0n@160by2.com";
    $to2 = "john.anderton@gmail.com";
    $subject = "";
    $message = "Next2: \"$nextMatchInfo[3]\" on \"$nextMatchInfo[1]\" at \"$nextMatchInfo[4]\" for $competition";
    $message = wordwrap($message, 70);

    $headers = "From: Update Messager <soccer.updates@isnewb.com>\r\n".
    "MIME-Version: 1.0\r\n".
    "Content-Type: text/plain;charset=iso-8859-1\r\n".
    "Content-Transfer-Encoding: 8bit\r\n";
    $mail_sent = mail($to, $subject, $message, $headers);
    mail($to2, $subject, $message, $headers);
    echo ($mail_sent ? "Mail sent" : "Mail failed")." to $to $headers<br />";
    echo $message."<br />";
    echo strlen($message)."<br />";
}

function out($val) {
    echo $val."<br />\n";
}
?>
