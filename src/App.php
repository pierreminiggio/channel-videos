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

            $channelVideoFolderLink = 'https://storage.miniggiodev.fr/youtube-likes-recap/channel/' . $channelId . '/';
            $channelVideoFolderCurl = curl_init($channelVideoFolderLink);
            curl_setopt($channelVideoFolderCurl, CURLOPT_RETURNTRANSFER, true);
            $channelVideoFolderCurlResponse = curl_exec($channelVideoFolderCurl);
            $channelVideoFolderCurlInfos = curl_getinfo($channelVideoFolderCurl);
            curl_close($channelVideoFolderCurl);

            $videos = [];
            if (isset($channelVideoFolderCurlInfos['http_code']) && $channelVideoFolderCurlInfos['http_code'] === 200) {
                
                $splitOnLinkStartOpens = explode('<a', $channelVideoFolderCurlResponse);

                foreach ($splitOnLinkStartOpens as $splitOnLinkStartOpenindex => $splitOnLinkStartOpen) {
                    if ($splitOnLinkStartOpenindex === 0) {
                        continue;
                    }

                    $splitOnLinkStartClose = explode('>', $splitOnLinkStartOpen, 2);

                    if (count($splitOnLinkStartClose) === 1) {
                        continue;
                    }

                    $afterLinkStartClose = $splitOnLinkStartClose[1];
                    
                    $splitOnLinkEndStart = explode('<', $afterLinkStartClose, 2);

                    if (count($splitOnLinkEndStart) === 1) {
                        continue;
                    }

                    $linkContent = $splitOnLinkEndStart[0];

                    if (! str_contains($linkContent, '.')) {
                        continue;
                    }

                    $videos[] = $linkContent;
                }
            }
            
            if (! $videos) {
                $videoHtml = 'Aucune';
            } else {
                $numberOfVideos = count($videos);
                $videoNames = implode(', ', $videos);
                $videoHtml = <<<HTML
                    <span title="$videoNames">$numberOfVideos</span>
                HTML;
            }

            $html .= <<<HTML
                    <li><a
                        href="https://youtube.com/channel/$channelId"
                        target="_blank"
                    >$channelName ({$mostLikedChannel['likes']})</a> <a
                        href="$channelVideoFolderLink"
                        target="_blank"
                    >$videoHtml</a></li>
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
