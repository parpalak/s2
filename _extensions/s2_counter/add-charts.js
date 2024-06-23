/**
 * Counter
 *
 * Creates chart on the client side
 *
 * @copyright (C) 2010-2024 Roman Parpalak
 * @license http://opensource.org/licenses/MIT MIT
 * @package s2_counter
 */
document.addEventListener('DOMContentLoaded', function () {
    fetch("../_extensions/s2_counter/data.php?file=arch_info.txt")
        .then(response => response.text())
        .then(textData => {
            let arrayData = textData.split("\n");
            let dataHits = [];
            let dataHosts = [];

            let count = arrayData.length;
            for (let i = 0; i < count; i++) {
                let items = arrayData[i].split("^");
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

            drawChart('s2_counter_hits', [
                {
                    name: 'Hits by day',
                    data: dataHits,
                    tooltip: {
                        valueDecimals: 0
                    }
                },
                {
                    name: 'Unique hosts by day',
                    data: dataHosts,
                    color: '#00751c',
                    tooltip: {
                        valueDecimals: 0
                    }
                },
            ]);
        });

    let collectedData = {};

    function collectRssData(key, data) {
        collectedData[key] = data;
        if (Object.keys(collectedData).length === 2) {
            drawChart('s2_counter_rss', [
                {
                    name: 'RSS readers of the main site',
                    data: collectedData['main'],
                    color: '#fd8205',
                    tooltip: {
                        valueDecimals: 0
                    }
                },
                {
                    name: 'RSS readers of blog',
                    data: collectedData['blog'],
                    color: '#c700f5',
                    tooltip: {
                        valueDecimals: 0
                    }
                },
            ]);
        }
    }

    fetch("../_extensions/s2_counter/data.php?file=rss_main.txt.log")
        .then(response => response.text())
        .then(textData => {
            let arrayData = textData.split("\n");
            let dataHits = [];

            let count = arrayData.length;
            for (let i = 0; i < count; i++) {
                let items = arrayData[i].split("^");
                if (items.length === 2) {
                    dataHits.push([
                        (new Date(items[0])).getTime(),
                        Number(items[1])
                    ]);
                }
            }

            collectRssData('main', dataHits);
        });

    fetch("../_extensions/s2_counter/data.php?file=rss_s2_blog.txt.log")
        .then(response => response.text())
        .then(textData => {
            let arrayData = textData.split("\n");
            let dataHits = [];

            let count = arrayData.length;
            for (let i = 0; i < count; i++) {
                let items = arrayData[i].split("^");
                if (items.length === 2) {
                    dataHits.push([
                        (new Date(items[0])).getTime(),
                        Number(items[1])
                    ]);
                }
            }

            collectRssData('blog', dataHits);
        });
});

function drawChart(id, series) {
    new Highcharts.stockChart(id, {
        rangeSelector: {
            selected: 1
        },
        chart: {
            panning: {
                enabled: true,
                type: 'x'
            },
            panKey: 'alt',
            zoomType: 'x'
        },
        legend: {
            enabled: true
        },
        series: series
    });
}