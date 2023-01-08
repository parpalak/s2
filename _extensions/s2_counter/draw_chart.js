/**
 * Counter
 *
 * Creates chart on the client side
 *
 * @copyright (C) 2010-2023 Roman Parpalak
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package s2_counter
 */

$(function () {
    var hitsLoaded = false, rssLoaded = false;

    $(document).on('stat_tab_loaded.s2', function () {
        if (!hitsLoaded) {
            GETAsyncRequest("../_extensions/s2_counter/data.php?file=arch_info.txt", function (http, textData) {
                var arrayData = textData.split("\n");
                var dataHits = [];
                var dataHosts = [];

                var count = arrayData.length;
                for (var i = 0; i < count; i++) {
                    var items = arrayData[i].split("^");
                    if (items.length === 3) {
                        dataHits.push([
                            (new Date(items[0])).getTime(),
                            Number(items[1])
                        ]);
                        dataHosts.push([
                            (new Date(items[0])).getTime(),
                            Number(items[2])
                        ]);
                    }
                }

                Highcharts.stockChart('s2_counter_hits', {
                    rangeSelector: {
                        selected: 1
                    },

                    legend: {
                        enabled: true,
                    },
                    series: [
                        {
                            name: 'Hits',
                            data: dataHits,
                            tooltip: {
                                valueDecimals: 0
                            }
                        },
                        {
                            name: 'Hosts',
                            data: dataHosts,
                            tooltip: {
                                valueDecimals: 0
                            }
                        },
                    ]
                });
            });

            if (!rssLoaded) {
                var collectedData = {};

                function collectRssData(key, data) {
                    collectedData[key] = data;
                    if (Object.keys(collectedData).length === 2) {
                        Highcharts.stockChart('s2_counter_rss', {
                            rangeSelector: {
                                selected: 1
                            },

                            legend: {
                                enabled: true,
                            },
                            series: [
                                {
                                    name: 'RSS main',
                                    data: collectedData['main'],
                                    tooltip: {
                                        valueDecimals: 0
                                    }
                                },
                                {
                                    name: 'RSS blog',
                                    data: collectedData['blog'],
                                    tooltip: {
                                        valueDecimals: 0
                                    }
                                },
                            ]
                        });
                    }
                }

                GETAsyncRequest("../_extensions/s2_counter/data.php?file=rss_main.txt.log", function (http, textData) {
                    var arrayData = textData.split("\n");
                    var dataHits = [];

                    var count = arrayData.length;
                    for (var i = 0; i < count; i++) {
                        var items = arrayData[i].split("^");
                        if (items.length === 2) {
                            dataHits.push([
                                (new Date(items[0])).getTime(),
                                Number(items[1])
                            ]);
                        }
                    }

                    collectRssData('main', dataHits);
                });

                GETAsyncRequest("../_extensions/s2_counter/data.php?file=rss_s2_blog.txt.log", function (http, textData) {
                    var arrayData = textData.split("\n");
                    var dataHits = [];

                    var count = arrayData.length;
                    for (var i = 0; i < count; i++) {
                        var items = arrayData[i].split("^");
                        if (items.length === 2) {
                            dataHits.push([
                                (new Date(items[0])).getTime(),
                                Number(items[1])
                            ]);
                        }
                    }

                    collectRssData('blog', dataHits);
                });
            }
        }
//        hitsLoaded = true; // TODO more accurate stat refresh
    });
});
