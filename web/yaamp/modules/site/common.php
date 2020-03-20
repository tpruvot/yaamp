<?php

JavascriptFile("/extensions/jqplot/jquery.jqplot.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.dateAxisRenderer.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.barRenderer.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.highlighter.js");

JavascriptFile("/yaamp/ui/js/jquery.metadata.js");
JavascriptFile("/yaamp/ui/js/jquery.tablesorter.widgets.js");

echo getAdminSideBarLinks();

//<a href='/site/eval'>Eval</a>&nbsp;
?>
<a href='/site/memcached'>Memcache</a>&nbsp;
<a href='/site/connections'>Connections</a>&nbsp;

<?php
if (YAAMP_RENTAL):
?>
<a href='/renting/admin'>Rental</a>&nbsp;
<?php
endif;
?>

<div id='main_results'></div>

<br><a href='/site/create'><img width=16 src=''><b>CREATE COIN</b></a>
<br><a href='/site/updateprice'><img width=16 src=''><b>UPDATE PRICE</b></a>

<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>

<srendererOptions: {barWidth: 3}
//         },

//         axes: {
//             xaxis: {
//                 tickInterval: 7200,
//                 renderer: $.jqplot.DateAxisRenderer,
/,
                tickOptions: {formatString: '<font size=1>%#Hh</font>'}
            },
            yaxis: {
                min: 0,
                tickOptions: {formatString: '<font size=1>%#.3f &nbsp;</font>'}
            }
        },

        grid:
        {
            borderWidth: 1,
            shadowWidth: 0,
            shadowDepth: 0,
            background: '#ffffff'
        },

    });
}

///////////////////////////////////////////////////////////////////////

// function main_ready_profit(data)
// {
//     graph_init_profit(data);
// }

// function main_refresh_profit()
// {
//     var url = "/site/graph_profit_results";
//     $.get(url, '', main_ready_profit);
// }

// function graph_init_profit(data)
// {
//     $('#graph_results_profit').empty();

//     var t = $.parseJSON(data);
//     var plot1 = $.jqplot('graph_results_profit', t,
//     {
//     //    title: '<b></b>',
//         stackSeries: true,

//         seriesDefaults:
//         {
//             renderer:$.jqplot.BarRenderer,
//             rendererOptions: {barWidth: 3}
//         },

//         axes: {
//             xaxis: {
//                 tickInterval: 7200,
//                 renderer: $.jqplot.DateAxisRenderer,
/,
                tickOptions: {formatString: '<font size=1>%#Hh</font>'}
            },
            yaxis: {
                min: 0,
                tickOptions: {formatString: '<font size=1>%#.3f &nbsp;</font>'}
            }
        },

        grid:
        {
            borderWidth: 1,
            shadowWidth: 0,
            shadowDepth: 0,
            background: '#ffffff'
        },

    });
}

///////////////////////////////////////////////////////////////////////

// function main_ready_profit(data)
// {
//     graph_init_profit(data);
// }

// function main_refresh_profit()
// {
//     var url = "/site/graph_profit_results";
//     $.get(url, '', main_ready_profit);
// }

// function graph_init_profit(data)
// {
//     $('#graph_results_profit').empty();

//     var t = $.parseJSON(data);
//     var plot1 = $.jqplot('graph_results_profit', t,
//     {
//     //    title: '<b></b>',
//         stackSeries: true,

//         seriesDefaults:
//         {
//             renderer:$.jqplot.BarRenderer,
//             rendererOptions: {barWidth: 3}
//         },

//         axes: {
//             xaxis: {
//                 tickInterval: 7200,
//                 renderer: $.jqplot.DateAxisRenderer,
/,
                tickOptions: {formatString: '<font size=1>%#Hh</font>'}
            },
            yaxis: {
                min: 0,
                tickOptions: {formatString: '<font size=1>%#.3f &nbsp;</font>'}
            }
        },

        grid:
        {
            borderWidth: 1,
            shadowWidth: 0,
            shadowDepth: 0,
            background: '#ffffff'
        },

    });
}

///////////////////////////////////////////////////////////////////////

// function main_ready_profit(data)
// {
//     graph_init_profit(data);
// }

// function main_refresh_profit()
// {
//     var url = "/site/graph_profit_results";
//     $.get(url, '', main_ready_profit);
// }

// function graph_init_profit(data)
// {
//     $('#graph_results_profit').empty();

//     var t = $.parseJSON(data);
//     var plot1 = $.jqplot('graph_results_profit', t,
//     {
//     //    title: '<b></b>',
//         stackSeries: true,

//         seriesDefaults:
//         {
//             renderer:$.jqplot.BarRenderer,
//             rendererOptions: {barWidth: 3}
//         },

//         axes: {
//             xaxis: {
//                 tickInterval: 7200,
//                 renderer: $.jqplot.DateAxisRenderer,
//                 tickOptions: {formatString: '<font size=1>%#Hh</font>'}
//             },
//             yaxis: {
//                 min: 0,
//                 tickOptions: {formatString: '<font size=1>%#.3f &nbsp;</font>'}
//             }
//         },

//         grid:
//         {
//             borderWidth: 1,
//             shadowWidth: 0,
//             shadowDepth: 0,
//             background: '#ffffff'
//         },

//     });
// }


</script>
