<?php

include 'connection.php';

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');

// Read raw data from the request body
$requestBody = file_get_contents('php://input');
// Decode JSON data
$jsonData = json_decode($requestBody);

// Show json response to the client
function sendResponse($resp_code, $videos, $channels, $message)
{
    echo json_encode(array('code' => $resp_code, 'message' => $message, 'channels' => $channels, 'videos' => $videos), JSON_PRETTY_PRINT);
}

if ($jsonData) {
    if (isset($jsonData->channel)) {
        $channelName = $jsonData->channel;
        if ($conn == null) {
            sendResponse(500, $conn, 'Server Connection Error');
        } else {
            $queryVideos = "SELECT * FROM youtube_channel_videos WHERE channel='$channelName'";
            $queryChannel = "SELECT * FROM youtube_channels";
            $videosResult = $conn->query($queryVideos);
            $channelResult = $conn->query($queryChannel);
            if ($videosResult->num_rows > 0 || $channelResult->num_rows > 0) {
                $videos = array();
                $channels = array();
                while ($row = $videosResult->fetch_assoc()) {
                    array_push($videos, array(
                        "id" =>  $row["id"],
                        "videoLink" => $row["videoLink"],
                        "title" => $row["title"],
                        "description" => $row["description"],
                        "thumbnail" => $row["thumbnail"],
                    ));
                }
                while ($row = $channelResult->fetch_assoc()) {
                    array_push($channels, array(
                        "profilePicture" => $row["profilePicture"],
                        "name" => $row["name"],
                        "description" => $row["description"],
                    ));
                }

                sendResponse(200, $videos, $channels, 'Channel information found!');
            } else {
                sendResponse(404, [], [], 'Channel not found or videos not available!');
            }
            //closing connection
            $conn->close();
        }
    } else {
        sendResponse(404, [], [], 'Channel not found!');
    }
} else {
    sendResponse(500, [], [], 'No request body found!');
}
