<!doctype html>
<html lang="en">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Skooliya Video Player</title>
        <style>
            /* body {
                font-family: sans-serif;
            } */

            .youtube-container {
                overflow: hidden;
                width: 100%;
                /* Keep it the right aspect-ratio */
                aspect-ratio: 16/9;
                /* No clicking/hover effects */
                pointer-events: none;
                
                iframe {
                    /* Extend it beyond the viewport... */
                    width: 300%;
                    height: 100%;
                    /* ...and bring it back again */
                    margin-left: -100%;
                }
            }

            .original-youtube-iframe {
                width: 100%;
                max-width: 560px;
                height: 315px;
            }
            .video-container-iframe {
                text-align: center;
                margin: 20px 0;
            }
        </style>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>

        <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>

        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/plyr/2.0.18/plyr.js"></script>
        <link rel="stylesheet" type="text/css" href="https://cdn.plyr.io/2.0.7/plyr.css">
    </head>
    <body>
        <!-- <h2>YouTube</h2> -->
        <div class="container-fluid">
            <div class="row">
            <!-- <div class="video-container-iframe">
                <iframe class="original-youtube-iframe" src="https://www.youtube.com/embed/{{-- $request['videoid'] --}}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            </div> -->
                <div class="col-md-12 pr-0 pl-0">
                    <div id="myVideo" data-type="youtube" data-video-id="{{$request['videoid']}}"> </div>
                </div>
            </div>
        </div>
        <!-- <div id="info"></div> -->
        <script>
            $(document).ready(function($) {
                var videoEl = $('#myVideo').get(),
                    player = plyr.setup(videoEl);
                // console.log("  >player:", player[0]);

                player[0].on('ready', function(event) {
                    var instance = event.detail.plyr;
                    // console.log("  >ready - player type: " + instance.getType());
                    // console.log("  >duration: " + instance.getDuration());
                    trace("ready - duration: " + instance.getDuration());
                });

                player[0].on('playing', function(event) {
                    var instance = event.detail.plyr;
                    // console.log("  >playing");
                    // console.log("  >duration: " + instance.getDuration());
                    trace("playing");
                });

                player[0].on('seeking', function(event) {
                    var instance = event.detail.plyr;
                    // console.log("  >seeking");
                    // console.log("  >position: " + instance.getCurrentTime() + "/" + instance.getDuration());
                });

                    player[0].on('seeked', function(event) {
                    var instance = event.detail.plyr;
                    // console.log("  >seeked");
                    // console.log("  >position: " + instance.getCurrentTime() + "/" + instance.getDuration());
                    trace("seek - position: " + instance.getCurrentTime() + "/" + instance.getDuration());
                });
                
                player[0].on('ended', function(event) {
                    var instance = event.detail.plyr;
                    // console.log("  >ended");
                    // console.log("  >duration: " + instance.getDuration());
                    trace("ended");
                });

            });

            function trace(txt) {
                // var t = $("#info").html() + "<br>" + txt;
                // $("#info").html(t);
            }
        </script>    
    </body>
</html>