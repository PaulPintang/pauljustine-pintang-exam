<?php

include 'connection.php';

// Handle Cors
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');

$api_key = 'AIzaSyA9WKpe9o9_oIFFJ7rtJeLTsSCSwNTYmS0';

// Get the request body data as JSON
$request_body = file_get_contents("php://input");
$data = json_decode($request_body);


function sendResponse($resp_code, $channel, $videos)
{
	echo json_encode(array('code' => $resp_code, 'channel' => $channel, 'videos' => $videos), JSON_PRETTY_PRINT);
}

function syncYTChannelVideos($conn, $ytChannel, $api_key)
{
	$all_videos = [];
	// Get the first 50 youtube channel videos
	$youtube_videos_first = file_get_contents('https://www.googleapis.com/youtube/v3/search?order=date&part=snippet&channelId=' . $ytChannel["channel_id"] . '&maxResults=50&key=' . $api_key);
	$videos_first = json_decode($youtube_videos_first, true);

	// Add the videos from the first request to the all_videos array
	$all_videos = array_merge($all_videos, $videos_first['items']);

	// Get the next 50 youtube channel videos (if available)
	if (isset($videos_first['nextPageToken'])) {
		$youtube_videos_next = file_get_contents('https://www.googleapis.com/youtube/v3/search?order=date&part=snippet&channelId=' . $ytChannel["channel_id"] . '&maxResults=50&pageToken=' . $videos_first['nextPageToken'] . '&key=' . $api_key);
		$videos_next = json_decode($youtube_videos_next, true);

		// Add the videos from the second request to the all_videos array
		$all_videos = array_merge($all_videos, $videos_next['items']);
	}

	foreach ($all_videos as $video) {
		$channel = $ytChannel["name"];
		$videoData = [
			"videoLink" => $conn->real_escape_string($video['id']['videoId']),
			"title" => $conn->real_escape_string($video['snippet']['title']),
			"description" => $conn->real_escape_string($video['snippet']['description']),
			"thumbnail" => $conn->real_escape_string($video['snippet']['thumbnails']['medium']['url']),
		];

		$videosArray[] = $videoData;

		$query = "INSERT INTO youtube_channel_videos (videoLink, title, description, thumbnail, channel)
		 VALUES ('https://www.youtube.com/watch?v={$videoData['videoLink']}', '{$videoData['title']}', '{$videoData['description']}', '{$videoData['thumbnail']}', '$channel')";
		$result = mysqli_query($conn, $query);

		if (!$result) {
			echo "Videos not added!";
		}
	}
	sendResponse(200, $ytChannel, $videosArray);
}

function syncYoutubeInfo($conn, $name, $description, $profilePicture, $channel_id, $api_key)
{
	$query = "INSERT INTO youtube_channels (profilePicture, name, description) VALUES ('$profilePicture', '$name', '$description')";
	if ($conn->query($query)) {
		$ytChannel = [
			"channel_id" => $channel_id,
			"name" => $name,
			"description" => $description,
			"profilePicture" => $profilePicture,
		];
		syncYTChannelVideos($conn, $ytChannel, $api_key);
	} else {
		echo json_encode(["message" => "Youtube channel sync failed: " . $conn->error], JSON_PRETTY_PRINT);
	}
}

if ($data) {
	if (isset($data->channel_id)) {
		$channel_id = $data->channel_id;
		$youtube_info = file_get_contents('https://youtube.googleapis.com/youtube/v3/channels?part=snippet&id=' . $channel_id . '&key=' . $api_key);
		$info = json_decode($youtube_info, true);
		if (isset($info['items'][0])) {
			$name = $conn->real_escape_string($info['items'][0]['snippet']['title']);
			$profilePicture = $conn->real_escape_string($info['items'][0]['snippet']['thumbnails']['medium']['url']);
			$description = $conn->real_escape_string($info['items'][0]['snippet']['description']);
			$check = "SELECT name FROM youtube_channels WHERE name='$name'";
			$result = $conn->query($check);
			if ($result->num_rows > 0) {
				echo json_encode(["error" => "Youtube channel already exist!"]);
			} else {
				syncYoutubeInfo($conn, $name, $description, $profilePicture, $channel_id, $api_key);
			}
		}
	} else {
		echo json_encode(["message" => "Channel id not found."], JSON_PRETTY_PRINT);
	}
} else {
	echo json_encode(["message" => "No channel id provided."], JSON_PRETTY_PRINT);
}
