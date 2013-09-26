<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Facebook Controller
 *
 * PHP version 5
 * LICENSE: This source file is subject to LGPL license 
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/copyleft/lesser.html
 * @author	   Ushahidi Team <team@ushahidi.com> 
 * @package	   Ushahidi - http://source.ushahididev.com
 * @copyright  Ushahidi - http://www.ushahidi.com
 * @license	   http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License (LGPL) 
*/

class Socialmedia_Gplus_Controller extends Controller
{
	const API_URL = "https://www.googleapis.com/plus/v1/activities?";

	var $service = null;

	public function __construct() {
		$this->service = ORM::factory("Service")
								->where("service_name", "SocialMedia GPlus")
								->find();
	}

	/**
	* Search function for Facebook
	* @param array $keywords Keyworkds for search
	* @param array[lat,lon,radius] $location Array with Geo point and radius to constrain search results
	* @param string $since yyyy-mm-dd Date to be used as since date on search
	*/
	public function search($keywords, $location, $since)
	{

		require dirname(__DIR__) . '/libraries/google-api-php-client/src/Google_Client.php';
		require dirname(__DIR__) . '/libraries/google-api-php-client/src/contrib/Google_PlusService.php';

		$settings = ORM::factory('socialmedia_settings')->where('setting', 'gplus_next_page_token')->find();

		$client = new Google_Client();
		$client->setApplicationName("Ushahidi - SocialMedia Gplus");

		$client->setClientId('281739857753.apps.googleusercontent.com');
		$client->setClientSecret(socialmedia_helper::getSetting('gplus_api_key'));
		$client->setDeveloperKey('AIzaSyBnz3xXmfBX5jt-Pa26L-oTcLPqa59a-So');

		$plus = new Google_PlusService($client);

		$params = array(
			'orderBy' => 'recent',
			'maxResults' => '20',
		);

		if (!is_null($settings->value)) 
		{
			$params["pageToken"] = $settings->value;
		}	

		$results = $plus->activities->search(join("|", $keywords), $params);
		$result = $this->parse($results);


		// Save new highest id
		$settings->setting =  'gplus_next_page_token';
		$settings->value = $results["nextPageToken"];
		$settings->save();
	}

	/**
	* Parses API results and inserts them on the database
	* @param array $array_result json arrayed result
	* @return null
	*/
	public function parse($array_result) {
		foreach ($array_result["items"] as $s) {

			$entry = Socialmedia_Message_Model::getMessage($s["id"], $this->service->id);

			// don't resave messages we already have
			if (! $entry->loaded) 
			{				
				$object = $s["object"];

				if (empty($object["content"])) {
					$object["content"] = "[" . Kohana::lang('gplus.empty') . "]";
				}

				// set message data
				$entry->setServiceId($this->service->id);
				$entry->setMessageFrom($this->service->service_name);				
				$entry->setMessageLevel($entry::STATUS_TOREVIEW);
				$entry->setMessageId($s["id"]);
				$entry->setMessageDetail($object["content"]);
				$date = strtotime($s["published"]);
				$entry->setMessageDate(date("Y-m-d H:i:s", $date));

				$entry->setAuthor(
					$s["actor"]["id"], 
					$s["actor"]["displayName"],
					null,
					null
				);

				$media = array();

				if (isset($object["attachments"])) 
				{
					foreach ($object["attachments"] as $obj) 
					{
						if ($obj["objectType"] == "hangout") continue;

						switch ($obj["objectType"]) {
							case "article":
								$index = "url";
								break;

							case "album":
								$index = "other";
								break;

							default:
								$index = $obj["objectType"];
						}
						
						$media[$index][] = $obj["url"];
					}
				}

				// geo data
				if (isset($s["geocode"]))
				{
					die($s["geocode"]);
					if (isset($s["place"]["location"]["latitude"])) 
					{
						$entry->setCoordinates($s["place"]["location"]["latitude"], $s["place"]["location"]["latitude"]);
					}
				}

				// save message and assign data to it
				$entry->save();

				$entry->addData("url", $s["url"]);
				$entry->addAssets($media);			
			}
		}

		return null;
	}
}
