<?php

if(!isset($argv[1])){
	echo 'usage: '.$argv[0]." input_file hint_level\n";
	echo "input_file : path to file need to generate wordwise \n";
	echo "hint_level : from 1 to 5 default is 5, 1 is less wordwise hint show - only hard word will have definition, 5 is all wordwise hints show\n";
	die();
}else{
	$bookfile = $argv[1];
	$bookpath = pathinfo($bookfile, PATHINFO_DIRNAME);
	$bookfilename = pathinfo($bookfile, PATHINFO_FILENAME);
	
	if(!isset($argv[2])){
		$hint_level = 5;
	}else{
		$hint_level = $argv[2];
	}
	
}

//Load Stop Words
echo "[+] Load Stop Words \n";
$stopwords = file('stopwords.txt', FILE_IGNORE_NEW_LINES);

//Load Dict from CSV
echo "[+] Load Wordwise Dict \n";
$lines = explode( "\n", file_get_contents( 'wordwise-dict.csv' ) );
$headers = str_getcsv( array_shift( $lines ) );
$data = array();
foreach ( $lines as $line ) {

	$row = array();

	foreach ( str_getcsv( $line ) as $key => $field )
		$row[ $headers[ $key ] ] = $field;

	$row = array_filter( $row );

	$data[] = $row;

}
$wordwise_dict = $data;

//clean temp
echo "[+] Clean old temps \n";
if(file_exists('book_dump.htmlz')){
	unlink('book_dump.htmlz');
}
if(file_exists('book_dump_html')){
	deleteDir('book_dump_html');
}

//Convert Book to HTML
echo "[+] Convert Book to HTML \n";
//shell_exec('ebook-convert .\everybodylies.mobi .\book_dump_html');
shell_exec('ebook-convert "'.$bookfile.'" .\book_dump.htmlz');
shell_exec('ebook-convert .\book_dump.htmlz .\book_dump_html');

//Get content
echo "[+] Load Book Contents \n";
$bookcontent = file_get_contents('book_dump_html/index1.html');
$bookcontent_arr = explode(" ",$bookcontent);

//Process Word
echo "[+] Process (".count($bookcontent_arr).") Words \n";
sleep(5);

for ($i=0; $i<=count($bookcontent_arr); $i++) { 

	if(isset($bookcontent_arr[$i]) AND $bookcontent_arr[$i] != ''){
		
		$word = cleanword($bookcontent_arr[$i]);

		//check is stopword ?		
		$is_stopword = array_search($word, $stopwords);
		if($is_stopword != FALSE){
			continue; //SKIP
		}

		//Search Word in Wordwise Dict - https://www.php.net/manual/en/function.array-search.php#116635
		$key_found = array_search(strtolower($word) , array_column($wordwise_dict, 'word'));
		//echo $key_found;
		//print_r($wordwise_dict[$key_found]);
		if($key_found != FALSE){

			$wordwise = $wordwise_dict[$key_found];

			//Check hint_level of current matched word
			if($wordwise['hint_level'] > $hint_level) continue; //SKIP all higher hint_level word

			echo "[>>] Processing Word: $i \n";

			echo "[#] bookcontent_arr[$i]: ".$bookcontent_arr[$i]." \n";

			//Replace Original Word with Wordwised
			$bookcontent_arr[$i] = preg_replace(
				'/('.$word.')/i',
				'<ruby>$1<rt>'.$wordwise['short_def'].'</rt></ruby>',
				$bookcontent_arr[$i]
				);

			echo "[#] word: ".$word." \n";
			echo "[#] bookcontent_arr REPLACED: ".$bookcontent_arr[$i]." \n";

		}

	}

}


//Create new book with Wordwised
echo "[+] Create New Book with Wordwised \n";
$new_bookcontent_with_wordwised = implode(' ', $bookcontent_arr);
file_put_contents('book_dump_html/index1.html', $new_bookcontent_with_wordwised);
shell_exec('ebook-convert .\book_dump_html\index1.html "'.$bookpath.'/'.$bookfilename.'-wordwised.epub"');
shell_exec('ebook-convert .\book_dump_html\index1.html "'.$bookpath.'/'.$bookfilename.'-wordwised.azw3"');
shell_exec('ebook-convert .\book_dump_html\index1.html "'.$bookpath.'/'.$bookfilename.'-wordwised.pdf"');

echo "[+] 3 book EPUB, AZW3, PDF with wordwise generated Done !\n";

function deleteDir($dirPath) {
    if (! is_dir($dirPath)) {
        throw new InvalidArgumentException("$dirPath must be a directory");
    }
    if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
        $dirPath .= '/';
    }
    $files = glob($dirPath . '*', GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) {
            deleteDir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dirPath);
}

function cleanword($word){

	$word = strip_tags($word); //strip html tags

	$specialchar = array(',','<','>',';','&','*','~','/','"','[',']','#','?','`','–','.',"'",'"','"','!','“','”',':','.'); // recheck when apply this rule, may conflict with standard URL because it trim all char like ? and # and /

    $word = str_replace($specialchar,'',$word); //strip special chars
    $word = preg_replace("/[^ \w]+/", '', $word); //strip special chars - all non word and non space characters
    //$word = strtolower($word); //lowercase URL

    return $word;

}

?>