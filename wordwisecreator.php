<?php

main($argv);

function main($argv) {
	if (!isset($argv[1])) {
	    echo "usage: ".$argv[0]." input_file hint_level\n";
	    echo "input_file : path to file need to generate wordwise \n";
	    echo "hint_level : from 1 to 5 default is 5, 1 is less wordwise hint show - only hard word will have definition, 5 is all wordwise hints show\n";
	    die();
	}

	$bookfile = $argv[1];
    if (!isset($argv[2])) {
        $hint_level = 5;
    } else {
        $hint_level = $argv[2];
    }
	echo "[+] Hint level: $hint_level \n";

	// Load Stop Words
	echo "[+] Load Stop Words \n";
	$stopwords = file("stopwords.txt", FILE_IGNORE_NEW_LINES);

	// Load Dict from CSV
	$wordwise_dict = loadWordwisedDict();

	// Clean temp
	echo "[+] Clean old temps \n";
	cleanTempData();

	$ebook_convert_cmd = getEbookConvertCmd();

	// Convert book to html
	convertBookToHtml($ebook_convert_cmd, $bookfile);

	// Process book content
	$bookcontent_arr = processBookContent($wordwise_dict, $stopwords, $hint_level);

	// Create book with wordwise
	createBookWithWordwised($ebook_convert_cmd, $bookfile, $bookcontent_arr);

	// Clean tempt data, who does need it
	cleanTempData();
}

function convertBookToHtml($ebook_convert_cmd, $bookfile) {
	echo "[+] Convert Book to HTML \n";
	//shell_exec(''.$ebook_convert_cmd.' everybodylies.mobi book_dump_html');
	shell_exec(''.$ebook_convert_cmd.' "'.$bookfile.'" book_dump.htmlz');
	shell_exec(''.$ebook_convert_cmd.' book_dump.htmlz book_dump_html');

	if (!file_exists("book_dump_html/index1.html")) {
	    die("Please check did you installed Calibre ? Can you run command ebook-convert in shell ? I cannot access command ebook-convert in your system shell, This script need Calibre to process ebook texts");
	}
}

function processBookContent($wordwise_dict, $stopwords, $hint_level) {
	// Get content
	echo "[+] Load Book Contents \n";
	$bookcontent = file_get_contents("book_dump_html/index1.html");
	$bookcontent_arr = explode(" ", $bookcontent);

	// Process Word
	echo "[+] Process (".count($bookcontent_arr).") Words \n";
	// sleep(1);

	$body_detected = false;
	for ($i = 0; $i <= count($bookcontent_arr); $i++) {
	    if (isset($bookcontent_arr[$i]) and $bookcontent_arr[$i] != "") {
	        // detect body tag
	        if (!$body_detected) {
	            if (str_contains($bookcontent_arr[$i], "<body")) {
	                $body_detected = true;
	            } else {
	                continue;
	            }
	        }

	        $word = cleanword($bookcontent_arr[$i]);

	        // Check is stopword ?
	        $is_stopword = array_search($word, $stopwords);
	        if ($is_stopword != false) {
	            continue; //SKIP
	        }

	        // Search Word in Wordwise Dict - https://www.php.net/manual/en/function.array-search.php#116635
	        $key_found = array_search(
	            strtolower($word),
	            array_column($wordwise_dict, "word")
	        );
	        // echo $key_found;
	        // print_r($wordwise_dict[$key_found]);
	        if ($key_found != false) {
	            $wordwise = $wordwise_dict[$key_found];

	            // Check hint_level of current matched word
	            if ($wordwise["hint_level"] > $hint_level) {
	                continue;
	            } // SKIP all higher hint_level word

	            // Replace Original Word with Wordwised
	            $bookcontent_arr[$i] = preg_replace(
	                "/(".$word.")/i",
	                '<ruby>$1<rt>'.$wordwise["short_def"]."</rt></ruby>",
	                $bookcontent_arr[$i]
	            );

	            echo "[#] ".$word." => ".$wordwise["short_def"]." \n";
	        }
	    }
	}

	return $bookcontent_arr;
}

function createBookWithWordwised($ebook_convert_cmd, $bookfile, $bookcontent_arr) {
    $bookpath = pathinfo($bookfile, PATHINFO_DIRNAME);
    $bookfilename = pathinfo($bookfile, PATHINFO_FILENAME);

    echo "[+] Create New Book with Wordwised \n";
    $new_bookcontent_with_wordwised = implode(" ", $bookcontent_arr);
    file_put_contents(
        "book_dump_html/index1.html",
        $new_bookcontent_with_wordwised
    );
    shell_exec(''.$ebook_convert_cmd.' book_dump_html/index1.html "'.$bookpath.'/'.$bookfilename.'-wordwised.epub" -m book_dump_html/content.opf');
    // shell_exec(''.$ebook_convert_cmd.' .\book_dump_html\index1.html "'.$bookpath.'/'.$bookfilename.'-wordwised.azw3"');
    // shell_exec(''.$ebook_convert_cmd.' .\book_dump_html\index1.html "'.$bookpath.'/'.$bookfilename.'-wordwised.pdf"');

    // echo "[+] 3 book EPUB, AZW3, PDF with wordwise generated Done !\n";
    echo "[+] The EPUB book with wordwise generated at \"".$bookpath."/".$bookfilename."-wordwised.epub\" \n";
}

function loadWordwisedDict() {
	echo "[+] Load Wordwise Dict \n";
	$lines = explode("\n", file_get_contents("wordwise-dict.csv"));
	$headers = str_getcsv(array_shift($lines));
	$data = [];
	foreach ($lines as $line) {
	    $row = [];
	    foreach (str_getcsv($line) as $key => $field) {
	        $row[$headers[$key]] = $field;
	    }

	    $row = array_filter($row);
	    $data[] = $row;
	}
	return $data;
}

function getEbookConvertCmd() {
	$cmd_name = 'ebook-convert';
	if (!isCmdToolExists($cmd_name)) {
		// try mac version
		$mac_cmd = '/Applications/calibre.app/Contents/MacOS/ebook-convert';
		if (isCmdToolExists($mac_cmd)) {
			$cmd_name = $mac_cmd;
		}
	}
	return $cmd_name;
}

function isCmdToolExists($tool_name) {
	$res = shell_exec("command -v ".$tool_name."");
	return !($res === null || trim($res) === '');
}

function cleanTempData() {
	if (file_exists("book_dump.htmlz")) {
	    unlink("book_dump.htmlz");
	}
	if (file_exists("book_dump_html")) {
	    deleteDir("book_dump_html");
	}
}

function deleteDir($dirPath) {
    if (!is_dir($dirPath)) {
        throw new InvalidArgumentException("$dirPath must be a directory");
    }
    if (substr($dirPath, strlen($dirPath) - 1, 1) != "/") {
        $dirPath .= "/";
    }
    $files = glob($dirPath . "*", GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) {
            deleteDir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dirPath);
}

function cleanword($word) {
    $word = strip_tags($word); //strip html tags

	// Recheck when apply this rule, may conflict with standard URL because it trim all char like ? and # and /
    $specialchar = array(',','<','>',';','&','*','~','/','"','[',']','#','?','`','–','.',"'",'"','"','!','“','”',':','.');


    $word = str_replace($specialchar, "", $word); //strip special chars
    $word = preg_replace("/[^ \w]+/", "", $word); //strip special chars - all non word and non space characters
    // $word = strtolower($word); //lowercase URL

    return $word;
}

?>
