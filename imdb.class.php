<?php
#https://github.com/abhinayrathore/PHP-IMDb-Scraper
class IMDb{

	static function getMovieInfo($title, $getExtraInfo = true){
		$imdbId = self::getIMDbIdFromSearch(trim($title));
		if($imdbId === NULL){
			$arr = array();
			$arr['error'] = "Nehnum título encontrado.";
			return $arr;
		}
		return self::getMovieInfoById($imdbId, $getExtraInfo);
	}

	static function getMovieInfoById($id,$getExtraInfo=true){ return self::scrapeMovieInfo('http://www.imdb.com/title/'.trim($id).'/',$getExtraInfo); }

	static function scrapeMovieInfo($imdbUrl, $getExtraInfo = true){
		$arr = array();
		$html = self::geturl("${imdbUrl}reference");
		$title_id = self::match('/<link rel="canonical" href="https:\/\/www.imdb.com\/title\/(tt\d+)\/reference" \/>/ms', $html, 1);
		if(empty($title_id) || !preg_match("/tt\d+/i", $title_id)) {
			$arr['error'] = "No Title found on IMDb!";
			return $arr;
		}
		$arr['title_id'] = $title_id;
		$arr['imdb_url'] = $imdbUrl;
		$arr['title'] = str_replace('"', '', trim(self::match('/<title>(IMDb \- )*(.*?) \(.*?<\/title>/ms', $html, 2)));
		$arr['year'] = trim(self::match('/<title>.*?\(.*?(\d{4}).*?\).*?<\/title>/ms', $html, 1));
		$arr['rating'] = self::match('/<span class="ipl-rating-star__rating">(\d.\d)<\/span>/ms', $html, 1);
		$arr['genres'] = self::match_all('/<a href="\/genre\/.*?">(.*?)<\/a>/ms', self::match('/<td.*?>Genres?<\/td>.*?<td>(.*?)<\/td>/ms', $html, 1), 1);
		$arr['directors'] = self::match_all_key_value('/<a href="\/name\/(nm\d+).*?>(.*?)<\/a>/ms', self::match('/Directed by.*?<\/h4>.*?<table.*?>(.*?)<\/table>/ms', $html, 1));
		$arr['writers'] = self::match_all_key_value('/<a href="\/name\/(nm\d+).*?>(.*?)<\/a>/ms', self::match('/Written by.*?<\/h4>.*?<table.*?>(.*?)<\/table>/ms', $html, 1));
		$arr['cast'] = self::match_all_key_value('/<a href="\/name\/(nm\d+).*?>(.*?)<\/a>/ms', self::match('/Cast.*?<\/h4>.*?<table.*?>(.*?)<\/table>/ms', $html, 1));
		$arr['cast'] = array_slice($arr['cast'], 0, 30);
		$arr['stars'] = self::match_all_key_value('/<a href="\/name\/(nm\d+).*?>(.*?)<\/a>/ms', self::match('/Stars:.*?<ul.*?>(.*?)<\/ul>/ms', $html, 1));
		$arr['producers'] = self::match_all_key_value('/<a href="\/name\/(nm\d+).*?>(.*?)<\/a>/ms', self::match('/Produced by.*?<\/h4>.*?<table.*?>(.*?)<\/table>/ms', $html, 1));
		$arr['musicians'] = self::match_all_key_value('/<a href="\/name\/(nm\d+).*?>(.*?)<\/a>/ms', self::match('/Music by.*?<\/h4>.*?<table.*?>(.*?)<\/table>/ms', $html, 1));
		$arr['cinematographers'] = self::match_all_key_value('/<a href="\/name\/(nm\d+).*?>(.*?)<\/a>/ms', self::match('/Cinematography by.*?<\/h4>.*?<table.*?>(.*?)<\/table>/ms', $html, 1));
		$arr['editors'] = self::match_all_key_value('/<a href="\/name\/(nm\d+).*?>(.*?)<\/a>/ms', self::match('/Film Editing by.*?<\/h4>.*?<table.*?>(.*?)<\/table>/ms', $html, 1));
		$arr['release_date'] = self::match('/<a href="\/title\/tt\d+\/releaseinfo">(.*?)<\/a>/ms', $html, 1);
		$arr['tagline'] = trim(self::match('/<td.*?>Taglines<\/td>.*?<td>(.*?)(<a|<\/div)/ms', $html, 1));
		$arr['plot'] = trim(strip_tags(self::match('/<td.*?>Plot Summary<\/td>.*?<td>.*?<p>(.*?)</ms', $html, 1)));
		$arr['plot_keywords'] = self::match_all('/<a href="\/keyword\/.*?">(.*?)<\/a>/ms', self::match('/<td.*?>Plot Keywords<\/td>(.*?)<\/ul>/ms', $html, 1), 1);
		$arr['poster'] = self::match('/<link rel=.image_src. href="(.*?)"/msi', $html, 1);
		$arr['poster_large'] = "";
		$arr['poster_full'] = "";
		if (!empty($arr['poster'])){
			$arr['poster'] = preg_replace('/_V1.*?.jpg/ms', "_V1._SY200.jpg", $arr['poster']);
			$arr['poster_large'] = preg_replace('/_V1.*?.jpg/ms', "_V1._SY500.jpg", $arr['poster']);
			$arr['poster_full'] = preg_replace('/_V1.*?.jpg/ms', "_V1._SY0.jpg", $arr['poster']);
		}
		$arr['runtime'] = trim(self::match('/<td.*?>Runtime<\/td>.*?<td>.*?<li.*?>(.*?)<\/li>/ms', $html, 1));
		$arr['top_250'] = trim(self::match('/Top Rated Movies: #(\d+)/ms', $html, 1));
		$arr['oscars'] = trim(self::match('/Won (\d+) Oscars?/ms', $html, 1));
		if(empty($arr['oscars']) && preg_match("/Won Oscar\./i", $html)) $arr['oscars'] = "1";
		$arr['awards'] = trim(self::match('/(\d+) wins/ms',$html, 1));
		$arr['nominations'] = trim(self::match('/(\d+) nominations/ms',$html, 1));
		$arr['language'] = self::match_all('/<a href="\/language\/.*?">(.*?)<\/a>/ms', self::match('/<td.*?>Language<\/td>.*?<td>(.*?)<\/td>/ms', $html, 1), 1);
		$arr['country'] = self::match_all('/<a href="\/country\/.*?">(.*?)<\/a>/ms', self::match('/<td.*?>Country<\/td>.*?<td>(.*?)<\/td>/ms', $html, 1), 1);

		if($getExtraInfo == true) {
			$releaseinfoHtml = self::geturl("https://www.imdb.com/title/" . $arr['title_id'] . "/releaseinfo");
			$arr['also_known_as'] = self::getAkaTitles($releaseinfoHtml);
			$arr['release_dates'] = self::getReleaseDates($releaseinfoHtml);
			$arr['media_images'] = self::getMediaImages($arr['title_id']);
			$arr['videos'] = self::getVideos($arr['title_id']);
		}

		return $arr;
	}

	static function getReleaseDates($h,$r=array()){
		foreach(self::match_all('/<tr.*?>(.*?)<\/tr>/ms', self::match('/<table id="release_dates".*?>(.*?)<\/table>/ms',$h,1),1) as $r){
			array_push($r, trim(strip_tags(self::match('/<td>(.*?)<\/td>/ms',$r,1)))." = ".trim(strip_tags(self::match('/<td class="release_date">(.*?)<\/td>/ms',$r,1))));
		}
		return array_filter($r);
	}

	static function getAkaTitles($h,$r=array()){
		foreach(self::match_all('/<tr.*?>(.*?)<\/tr>/msi', self::match('/<table id="akas".*?>(.*?)<\/table>/ms',$h,1),1) as $m){
			$a = self::match_all('/<td>(.*?)<\/td>/ms',$m,1);
			array_push($r, trim($a[1])." = ".trim($a[0]));
		}
		return array_filter($r);
	}

	static function getMediaImages($id,$r=array()){
		$u = "https://www.imdb.com/title/$id/mediaindex";
		$h = self::geturl($u);
		$r = array_merge($r, self::scanMediaImages($h));
		foreach(self::match_all('/<a.*?>(\d*)<\/a>/ms', self::match('/<span class="page_list">(.*?)<\/span>/ms',$h,1),1) as $p){
			$r = array_merge($r, self::scanMediaImages(self::geturl("$u?page=$p")));
		}
		return $r;
	}

	static function scanMediaImages($h,$r){
		foreach(self::match_all('/src="(.*?)"/msi', self::match('/<div class="media_index_thumb_list".*?>(.*?)<\/div>/msi',$h,1),1) as $i){
			array_push($r,preg_replace('/_V1\..*?.jpg/ms',"_V1._SY0.jpg",$i));
		}
		return array_filter($r);
	}

	static function getVideos($id,$v=array()){
		foreach (self::match_all('/<a.*?href="\/videoplayer\/(vi\d+).*?".*?>.*?<\/a>/ms', self::geturl("https://www.imdb.com/title/$id/videogallery"), 1) as $v){
			$v[] = "https://www.imdb.com/video/imdb/$v";
		}
		return array_filter($v);
	}

	static function getTop250($r=array(),$i=1){
		foreach (self::match_all('/<tr class="(even|odd)">(.*?)<\/tr>/ms', self::geturl("https://www.imdb.com/chart/top"), 2) as $m) {
			$r[] = array("id"=>self::match('/<td class="titleColumn">.*?<a href="\/title\/(tt\d+)\/.*?"/msi', $m, 1), "rank"=>$i, "title"=>self::match('/<td class="titleColumn">.*?<a.*?>(.*?)<\/a>/msi', $m, 1), "year"=>self::match('/<td class="titleColumn">.*?<span class="secondaryInfo">\((.*?)\)<\/span>/msi', $m, 1), "rating"=>self::match('/<td class="ratingColumn"><strong.*?>(.*?)<\/strong>/msi', $m, 1), "poster"=>preg_replace('/_V1.*?.jpg/ms', "_V1._SY200.jpg", self::match('/<td class="posterColumn">.*?<img src="(.*?)"/msi', $m, 1)));
			$i++;
		}
		return $r;
	}

	static function getIMDbIdFromSearch($t,$e="google"){
		switch ($e){
			case "google":
				$n = "bing"; break;
			case "bing":
				$n = "ask"; break;
			default: case "ask": case false:
				$n = false; break;
		}
		$m = self::match_all('/<a.*?href="http:\/\/www.imdb.com\/title\/(tt\d+).*?".*?>.*?<\/a>/ms', self::geturl("http://www.${e}.com/search?q=imdb+".rawurlencode($t)), 1);
		return !isset($m[0]) || empty($m[0]) && $n ? self::getIMDbIdFromSearch($t,$n) : $m[0]; 
	}

	static function geturl($url){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		$ip = rand(0,255).'.'.rand(0,255).'.'.rand(0,255).'.'.rand(0,255);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("REMOTE_ADDR: $ip", "HTTP_X_FORWARDED_FOR: $ip"));
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/".rand(3,5).".".rand(0,3)." (Windows NT ".rand(3,5).".".rand(0,2)."; rv:2.0.1) Gecko/20100101 Firefox/".rand(3,5).".0.1");
		$html = curl_exec($ch);
		curl_close($ch);
		return $html;
	}

	static function match_all_key_value($r,$s,$k=1,$v=2,$a=array()){
		preg_match_all($r,$s,$m,PREG_SET_ORDER);
		foreach($m as $i) $a[$i[$k]] = $i[$v];
		return $a;
	}

	static function match($r,$s,$i=0){ return preg_match($r,$s,$m) == 1 ? $m[$i] : false; }
	static function match_all($r,$s,$i=0){ return preg_match_all($r,$s,$m) === false ? false : $m[$i]; }
}