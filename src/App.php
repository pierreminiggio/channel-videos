<?php

namespace App;

use NeutronStars\Database\Query;
use PierreMiniggio\DatabaseConnection\DatabaseConnection;
use PierreMiniggio\DatabaseFetcher\DatabaseFetcher;

class App
{

    public function run(): void
    {

        $projectFolder =
            __DIR__
            . DIRECTORY_SEPARATOR
            . '..'
            . DIRECTORY_SEPARATOR
        ;
        $config = require
            $projectFolder
            . 'config.php'
        ;

        $dbConfig = $config['db'];

        $fetcher = new DatabaseFetcher(new DatabaseConnection(
            $dbConfig['host'],
            $dbConfig['database'],
            $dbConfig['username'],
            $dbConfig['password']
        ));

        $mostLikedChannels = $fetcher->query(
            $fetcher->createQuery(
                'social__youtube'
            )->select(
                'channel_id',
                'COUNT(id) as likes'
            )->where(
                'channel_id IS NOT NULL'
            )->groupBy(
                'channel_id'
            )->orderBy(
                'COUNT(id)',
                Query::ORDER_BY_DESC
            )
        );

        $html = <<<HTML
        <head><title>Vidéos des chaînes</title></head>
        <body>
            <h1>Vidéos des chaînes</h1>
            <ul>
        HTML;

        foreach ($mostLikedChannels as $mostLikedChannel) {
            $channelId = $mostLikedChannel['channel_id'];
            $channelInfosCurl = curl_init(
                'https://youtube-channel-infos-api.miniggiodev.fr/' . $channelId
            );
            curl_setopt($channelInfosCurl, CURLOPT_RETURNTRANSFER, true);
            $channelInfosCurlResponse = curl_exec($channelInfosCurl);
            $channelInfosCurlInfos = curl_getinfo($channelInfosCurl);
            curl_close($channelInfosCurl);

            $channelName = 'Unknown Name (' . $channelId . ')';
            if (isset($channelInfosCurlInfos['http_code']) && $channelInfosCurlInfos['http_code'] === 200) {
                if (! empty($channelInfosCurlResponse)) {
                    $channelInfosCurlJsonResponse = json_decode($channelInfosCurlResponse, true);

                    if (
                        ! empty($channelInfosCurlJsonResponse)
                        && isset($channelInfosCurlJsonResponse['title'])
                    ) {
                        $channelName = $channelInfosCurlJsonResponse['title'];
                    }
                }
            }

            $html .= <<<HTML
                <li><a
                    href="https://youtube.com/channel/$channelId"
                    target="_blank"
                >$channelName ({$mostLikedChannel['likes']})</a></li>
            </body>
        HTML;
        }

        $html .= <<<HTML
            </ul>
        </body>
        HTML;

        echo $html;
    }
}
