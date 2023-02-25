<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    
    <title>Import Database</title>
    <style>
        .body-content {
            padding-left: 5px;
            padding-right: 5px;
        }
        body {
            padding-top: 20px;
            padding-bottom: 20px;
        }
    </style>
  </head>
  <body  class="body-content">
    <div class="container">
    <form id="database-names">
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <input type="text" class="form-control" id="from_database" placeholder="From Database" name="from_database" required>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <input type="text" class="form-control" id="to_database" placeholder="To Database" name="to_database" required>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <input type="submit" class="btn btn-primary" value="Submit">
                </div>
            </div>
        </div>
    </form>
        <div class="row">
            <table class="table">
                <thead>
                    <tr>
                    <th scope="col">#</th>
                    <th scope="col">From</th>
                    <th scope="col">To-0</th>
                    <th scope="col">To-1</th>
                    <th scope="col">To-2</th>
                    <th scope="col">Action</th>
                    <th scope="col">#</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=0;?>
                    @foreach(importTableNames() as $key=>$val)
                    <?php $i++;?>
                    <tr>
                        <form action="{{$val['url']}}" class="sub_form">
                            <th scope="row">{{$i}}</th>
                            <td><input type="hidden" name="from" value="{{$val['from']}}">{{$val['from']}}</td>
                            <td><input type="hidden" name="to_0" value="{{@$val['to'][0]}}">{{@$val['to'][0]}}</td>
                            <td><input type="hidden" name="to_1" value="{{@$val['to'][1]}}">{{@$val['to'][1]}}</td>
                            <td><input type="hidden" name="to_2" value="{{@$val['to'][2]}}">{{@$val['to'][2]}}</td>
                            <td><button type="submit" class="btn btn-sm btn-primary">Import</button></td>
                            <td class="pagination"></td>
                        </form>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <!-- Optional JavaScript -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
    <script>
        $("#database-names").submit(function( event ) {
            event.preventDefault();
            
            // var $row = $(this).closest("tr");
            // var databaseFrom = $("#database-names").serialize();

            var data = $(this).serialize();     
            
            $.ajax({
                url: '/import-database/setSchoolDatabase',
                type: 'post',
                headers: {'X-CSRF-TOKEN': "{{ csrf_token() }}"},
                data : data,  //pass the CSRF_TOKEN()
                processData: false,
                dataType: 'json',
                beforeSend: function(){            
                },
                complete: function() {
                },
                success: function(response) {
                    if(response.status==true){
                        $('#database-names input').attr('readonly', true);
                    }
                    alert(response.msg);
                }
            });
        });
        $(".sub_form").submit(function( event ) {
            event.preventDefault();
            // console.log($(this).closest("tr").find("td.pagination").text("ok"));
            // return 0;
            // $('#database-names input').attr('disabled', true);
            var $row = $(this).closest("tr");
            var databaseFrom = $("#database-names").serialize();

            var data = $(this).serialize();     
            
            $.ajax({
                url: $(this).attr('action')+'?'+databaseFrom,
                type: 'get',
                headers: {'X-CSRF-TOKEN': "{{ csrf_token() }}"},
                data : data,  //pass the CSRF_TOKEN()
                processData: false,
                dataType: 'json',
                beforeSend: function(){            
                },
                complete: function() {
                },
                success: function(response) {
                    if(response.status==true){
                        // console.log(response.data);
                        pagi = '<ul class="pagination">';
                        $.each(response.data.links, function(key, value){
                            pagi +=  '<li class="page-item"><a class="page-link" style="'+(key==1?"background-color:aqua":"")+'" href="javascript:void(0)" onclick="submitPaginationForm(`'+value['url']+'`,this)">'+(value['label']=="&laquo; Previous"?"&laquo;":(value['label']=="Next &raquo;")?"&raquo;":value['label'])+'</a></li>';
                        });
                        pagi += '</ul>';
                        $row.find("td.pagination").html(pagi);
                    }
                    alert(response.msg);
                }
            });
        });

        function submitPaginationForm(geturl,this_but){
            if(geturl!=null&&geturl!="null"&&geturl!=""){
                // var $row = $(this).closest("tr");
                var databaseFrom = $("#database-names").serialize();

                // var data = $(this).serialize();     
                
                $.ajax({
                    url: geturl,
                    type: 'get',
                    headers: {'X-CSRF-TOKEN': "{{ csrf_token() }}"},
                    data : databaseFrom,  //pass the CSRF_TOKEN()
                    processData: false,
                    dataType: 'json',
                    beforeSend: function(){            
                    },
                    complete: function() {
                    },
                    success: function(response) {
                        // console.log(response);
                        if(response.status==true){
                            $(this_but).css("background-color","aqua");
                        }else if(response.status==false){
                            $(this_but).css("background-color","bisque");
                        }
                        alert(response.msg);
                    }
                });
            }
        }
    </script>
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
    
  </body>
</html>