<!DOCTYPE html>
<html>

<head>

    <META Http-Equiv="Cache-Control" Content="no-cache private no-cache must-revalidate" />
    <META Http-Equiv="Cache-Control" Content=" pre-check=0 post-check=0 max-age=0 max-stale = 0" />
    <META Http-Equiv="Pragma" Content="no-cache" />
    <META Http-Equiv="Expires" Content="0" />
    <meta charset="UTF-8">
    <title> Skooliya</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <style>
        .nav-tabs-custom>.nav-tabs {
            display: flex;
            justify-content: space-between;
            border: none;
            /* Remove border */
        }

        .nav-tabs-custom>.nav-tabs>ul {
            margin: 0;
            padding: 0;
            list-style: none;
            display: flex;
            /* Use Flexbox to distribute tabs evenly */
        }

        .nav-tabs-custom>.nav-tabs>li {
            /* background: #222; */
            color: white;
            list-style: none;
            margin: 0;
            /* padding: 20px; */
            border-right: 1px solid rgba(0, 0, 0, 0.1);
            /* font-family: Helvetica, Arial, sans-serif; */
            /* letter-spacing: 3px; */
            cursor: pointer;
            flex-grow: 1;
            /* Allow tabs to grow equally */
            text-align: center;
            /* Center the tab content */
        }

        .nav-tabs-custom>.nav-tabs>li>a {
            color: #b9b9b9;
            background: transparent;
            margin: 0;
            padding: 7px 15px;
            font-size: 17px;
        }

        .nav-tabs-custom>.nav-tabs>li.active {
            /* border-top-color: #3c8dbc; */
            border-top: 0px;
            /* border-bottom-color: #3c8dbc; */
            border-bottom: 3px solid #3c8dbc;
        }

        .nav-tabs-custom>.nav-tabs>li:last-child {
            /* box-shadow: inset 0 -50px 50px -50px rgba(0, 0, 0, 0.2), inset 1px 0 0 rgba(250, 255, 255, .1); */
            border-right: none;
            /* Remove the right border on the last tab */
        }

        /* .nav-tabs-custom > .nav-tabs > li > a:hover {
        color: #000;
    } */

        .special-list {
            margin-bottom: 10px;
            /* padding: 10px 15px; */
            padding: 10px 15px 10px 4px;
            line-height: 35px;
            border: 2px solid #d5d5d5;
            border-radius: 5px;
            font-size: 18px;
            font-weight: 600;
            box-shadow: rgba(0, 0, 0, 0.2) 10px 10px 20px -12px;
        }

        /* success list background changedfor this page */
        .success-list-custom {
            background-color: #ffdb92;
            display: flex;
            align-items: center;
        }

        .danger-list {
            background-color: #ff171747;
            display: flex;
            align-items: center;
        }

        /* circle badge changed for this page */
        .circle-badge-custom {
            margin-right: 15px;
            margin-left: 3px;
            width: 50px;
            height: 50px;
            line-height: 50px;
            border-radius: 50%;
            color: #fff;
            text-align: center;
            background: #f39c12;
            font-weight: 600;
            font-size: 20px;
        }

        .flex-row {
            flex-grow: 1;
            line-height: 17px;
        }

        .justify-between {
            display: flex;
            justify-content: space-between;
        }

        .info-panel {
            padding: 5px;
            margin-bottom: 10px;
        }

        .f-s-15 {
            font-size: 15px;
        }

        .f-s-17 {
            font-size: 17px;
        }
        .f-s-18 {
            font-size: 18px;
        }

        .f-s-25 {
            font-size: 25px;
        }

        .f-w-5 {
            font-weight: 500;
        }

        .f-w-6 {
            font-weight: 600;
        }

        .total-due-panel {
            padding: 5px;
            background-color: #ffabab;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 10px;
        }
        .till-due-panel {
            padding: 5px;
            background-color: #ff8787;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 10px;
        }
        .total-paid-panel {
            padding: 5px;
            background-color: #93ff95;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 10px;
        }

        .custom-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        .date-input-header{
            border-color: #000000 !important;
            font-size: 1.2em !important;
            font-weight: 600;
        }
        .title-overlap{
            position: absolute; 
            top: -14px; 
            left: 20px; 
            background-color: white; 
            padding-left: 0.5rem; 
            padding-right: 0.5rem; 
            font-weight: 600; 
            z-index: 7;
        }

    </style>
    <link rel="stylesheet" type="text/css"
        href="https://adminlte.io/themes/AdminLTE/bower_components/bootstrap/dist/css/bootstrap.min.css">
    
    <link rel="stylesheet" href="https://adminlte.io/themes/AdminLTE/bower_components/bootstrap/dist/css/bootstrap.min.css">

    <link rel="stylesheet" href="https://adminlte.io/themes/AdminLTE/bower_components/font-awesome/css/font-awesome.min.css">

    <link rel="stylesheet" href="https://adminlte.io/themes/AdminLTE/bower_components/Ionicons/css/ionicons.min.css">

    <link rel="stylesheet" href="https://adminlte.io/themes/AdminLTE/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css">

    <link rel="stylesheet" type="text/css" href="https://web.skooliya.com/css/AdminLTE.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script type="text/javascript" src="https://web.skooliya.com/js/bootstrap.js"></script>
    <script type="text/javascript" src="https://web.skooliya.com/js/AdminLTE/jquery.slimscroll.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.2/jquery-confirm.min.css">
    <script src="https://cdn.jsdelivr.net/npm/gasparesganga-jquery-loading-overlay@2.1.7/dist/loadingoverlay.min.js"></script>
    <script src="https://adminlte.io/themes/AdminLTE/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js"></script>
    
<script src="https://adminlte.io/themes/AdminLTE/plugins/input-mask/jquery.inputmask.js"></script>
<script src="https://adminlte.io/themes/AdminLTE/plugins/input-mask/jquery.inputmask.date.extensions.js"></script>
<script src="https://adminlte.io/themes/AdminLTE/plugins/input-mask/jquery.inputmask.extensions.js"></script>
<script>
    function setSection(classid,sectionid,allclasswithsection){
        allclasswithsection = JSON.parse(allclasswithsection);
        classid = "#"+classid;
        sectionid = "#"+sectionid;
        $(sectionid).children('option').remove();
        $(sectionid).append(new Option("Select Section", "")).change();
        // alert($(classid).val());
        if($(classid).val()!=''){
            // console.log(allclasswithsection.find( record => record.class === $(classid).val()))
            $.each(allclasswithsection.find( record => record.class === $(classid).val()).section, function(i, sec) {
                if(i==0){
                    $(sectionid).append(new Option('All', 'All')).change();
                }
                // $(sectionid).append(new Option(properCase(sec), sec)).change();
                $(sectionid).append(new Option(sec, sec)).change();
            });
        }
    }
</script>
</head>

<body class="skin-red sidebar-mini fixed">
    <section class="content">
        <form action="#" method="post" id="fee_day_book_form">
            <!-- <div class="row" style="margin-bottom: 10px;">
                <div class="col-xs-6">
                    <span class="block input-icon input-icon-right">
                        <div class="title-overlap">From Date <span class="text-danger font-weight-bold">*</span>
                        </div>
                        <div class="input-group date">
                            <input type="text" class="form-control date-input-header datepicker" name="from_date" placeholder="dd-mm-yyyy" id="from_date" data-inputmask="'alias': 'dd-mm-yyyy'" data-mask required>
                            <div class="input-group-addon" style="border-color: #000000;">
                            <i class="fa fa-calendar"></i>
                            </div>
                        </div>
                    </span>
                </div>
                <div class="col-xs-6">
                    <span class="block input-icon input-icon-right">
                        <div class="title-overlap">To Date <span class="text-danger font-weight-bold">*</span>
                        </div>
                        <span role="status" aria-live="polite" class="ui-helper-hidden-accessible"></span>
                        <div class="input-group date">
                            <input type="text" class="form-control date-input-header datepicker" name="to_date" placeholder="dd-mm-yyyy" id="to_date" data-inputmask="'alias': 'dd-mm-yyyy'" data-mask required>
                            <div class="input-group-addon" style="border-color: #000000;">
                                <i class="fa fa-calendar"></i>
                            </div>
                        </div>
                    </span>
                </div>
            </div> -->
            <div class="row" style="margin-bottom: 10px;">
                <div class="col-xs-6">
                    <select id="class" name="class" class="form-control"  onchange="setSection('class','section','{{json_encode($assigned_class['permittedclass'])}}')" required>
                        <option value="" selected>Select Class</option>
                        @foreach($assigned_class['permittedclass'] as $class)
                            <option value="{{ $class['class'] }}">{{ $class['class'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xs-6">
                    <select id="section" name="section" class="form-control" required>
                        <option value="" selected disabled>Select Section</option>
                    </select>
                </div>
            </div>
            <div class="row" style="margin-bottom: 10px;">
                <div class="col-xs-12">
                    <button type="submit" class="col-xs-12 btn btn-info">Get Record</button>
                </div>
            </div>
        </form>
        <div class="row">
            <div class="col-xs-4" style="padding-right: 2px;">
                <div class="total-paid-panel">
                    <div class="f-s-17 f-w-5">Total Paid</div>
                    <div class="f-s-18 f-w-6" id="total-collection">₹0</div>
                </div>
            </div>
            <div class="col-xs-4" style="padding-right: 2px;padding-left: 2px;">
                <div class="till-due-panel">
                    <div class="f-s-17 f-w-5">Till Due</div>
                    <div class="f-s-18 f-w-6" id="till-due">₹0</div>
                </div>
            </div>
            <div class="col-xs-4" style="padding-left: 2px;">
                <div class="total-due-panel">
                    <div class="f-s-17 f-w-5">Total Due</div>
                    <div class="f-s-18 f-w-6" id="total-due">₹0</div>
                </div>
            </div>
        </div>
        <div class="row" style="max-height: 100vh;overflow: auto;">
            <div class="col-md-12">
                <ul class="custom-list" id="fee-day-book-list">
                </ul>
            </div>
        </div>
    </section>

    <script>
        $(document).ajaxStart(function(){
            $.LoadingOverlay("show");
        });
        $(document).ajaxStop(function(){
            $.LoadingOverlay("hide");
        });
		$('.datepicker').datepicker({
			autoclose: true,
			format: "dd-mm-yyyy",
			// immediateUpdates: true,
			todayBtn: true,
			todayHighlight: true
		}).datepicker("setDate", "0");
        
		$('.datepicker').inputmask('dd-mm-yyyy', { 'placeholder': 'dd-mm-yyyy' });

        $("#fee_day_book_form").on("submit", function (e) {
			e.preventDefault(); // Prevent the form from submitting normally
			$.ajax({
				type: 'POST',
				url:   window.location.href,
				data: $(this).serialize(),
				dataType: 'json',
				beforeSend: function (xhr) {
                    xhr.setRequestHeader('Authorization', "{{$auth_token}}");
                },
				complete: function(data) {
					// $.LoadingOverlay("hide");
				},
				success: function(data) {
                    var total_paid_amount = 0;
                    var tillDue = 0;
                    var totalDue = 0;
                    var list = '';

                    $.each(data,function(k,ph){
                        // console.log(JSON.parse(ph['totalPaid']));
                        
                        var totalpaidsum = 0;
                        tpaid = ph['totalPaid']===null?'[]':ph['totalPaid'];
                        totalpaidsum = JSON.parse(tpaid).reduce((acc, cur) => acc + cur.paid_amount, 0);
                        list += `<li class="special-list success-list-custom"
                            data-companyid='`+ph['session']+`'
                            data-studentid='`+ph['AppID']+`'>
                                <div class="circle-badge-custom">`+(k+1)+`</div>
                                <div class="flex-row">
                                    <div class="justify-between" style="padding: 2px 0px 2px 0px;">
                                        <div>`+properCase(ph['StudentName'])+`</div>
                                        <div>`+ph['Class']+` - `+ph['Section']+`</div>
                                    </div>
                                    <div class="justify-between" style="padding: 2px 0px 2px 0px;">
                                        <div>`+properCase(ph['FatherName'])+`</div>
                                    </div>
                                    <div class="justify-between" style="padding: 2px 0px 2px 0px;">
                                        <div style="color: #008502;">₹`+(new Intl.NumberFormat('en-IN', { maximumSignificantDigits: 3 }).format(totalpaidsum))+`</div>
                                        <div style="color: #f75c5c;">₹`+(new Intl.NumberFormat('en-IN', { maximumSignificantDigits: 3 }).format(ph['tillDue']))+`</div>
                                        <div style="color: #c50000;font-weight: 800;">₹`+(new Intl.NumberFormat('en-IN', { maximumSignificantDigits: 3 }).format(ph['totalDue']))+`</div>
                                    </div>
                                </div>
                            </li>`;
                        total_paid_amount +=totalpaidsum;
                        tillDue += ph['tillDue'];
                        totalDue += ph['totalDue'];
                    });
                    $('#total-collection').text("₹"+(new Intl.NumberFormat('en-IN', { maximumSignificantDigits: 3 }).format(total_paid_amount)));
                    $('#till-due').text("₹"+(new Intl.NumberFormat('en-IN', { maximumSignificantDigits: 3 }).format(tillDue)));
                    $('#total-due').text("₹"+(new Intl.NumberFormat('en-IN', { maximumSignificantDigits: 3 }).format(totalDue)));
                    $('#fee-day-book-list').html(list);
                    $('.success-list-custom').on('click', function(){
                        console.log(window.location,window.location.origin,window.location.search);
                        var origin = window.location.origin;
                        var path = "/api/studentfeecard.php";
                        var variables = window.location.search;
                        var studentid = $(this).data('studentid');
                        var jc = $.confirm({
                            columnClass: 'medium',
                            title: false,
                            scrollToPreviousElement: false, // add this line 
                            scrollToPreviousElementAnimate: false, // add this line 
                            content: "URL:"+origin+path+variables+"&studentid="+studentid,
                            buttons: {
                                close: function () {
                                    //close
                                },
                            },
                        });
                    });
				},
			});
        });

        function getIndianCurrency(number) {
            var decimal = Math.round((number - (no = Math.floor(number))) * 100);
            var hundred = '';
            var digits_length = no.toString().length;
            var i = 0;
            var str = [];
            var words = {
                0: '', 1: 'one', 2: 'two', 3: 'three', 4: 'four', 5: 'five', 6: 'six',
                7: 'seven', 8: 'eight', 9: 'nine', 10: 'ten', 11: 'eleven', 12: 'twelve',
                13: 'thirteen', 14: 'fourteen', 15: 'fifteen', 16: 'sixteen', 17: 'seventeen',
                18: 'eighteen', 19: 'nineteen', 20: 'twenty', 30: 'thirty', 40: 'forty',
                50: 'fifty', 60: 'sixty', 70: 'seventy', 80: 'eighty', 90: 'ninety'
            };

            var digits = ['', 'hundred', 'thousand', 'lakh', 'crore'];

            while (i < digits_length) {
                var divider = (i === 2) ? 10 : 100;
                number = Math.floor(no % divider);
                no = Math.floor(no / divider);
                i += (divider === 10) ? 1 : 2;

                if (number) {
                    var plural = (str.length && number > 9) ? 's' : '';
                    hundred = (str.length === 1 && str[0]) ? ' and ' : '';
                    str.push((number < 21) ? words[number] + ' ' + digits[str.length] + plural + ' ' + hundred : words[Math.floor(number / 10) * 10] + ' ' + words[number % 10] + ' ' + digits[str.length] + plural + ' ' + hundred);
                } else {
                    str.push('');
                }
            }

            var Rupees = str.reverse().join('');
            // var paise = (decimal > 0) ? "." + (words[Math.floor(decimal / 10)] + " " + words[decimal % 10]) + ' Paise' : '';

            var paise = (decimal > 0) ? "" + (words[Math.floor(decimal / 10)] + " " + words[decimal % 10]) + ' Paise' : '';

            return (Rupees ? Rupees + ' Rupees ' : '') + paise;
        }
    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.31.3/js/jquery.tablesorter.min.js">
    </script>
    <script type="text/JavaScript" src="https://cdnjs.cloudflare.com/ajax/libs/jQuery.print/1.6.0/jQuery.print.js">
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.2/jquery-confirm.min.js"></script>


    <script>
        $(document).ready(function () {
            if ($('input[type="text"]').first()) {

            } else {
                $('#class').first().focus();
                $('#txtSec').first().focus();
            }

            $('input, textarea,redio,checkbox').each(
                function (index) {
                    //   var input = $(this);
                    if ($(this).attr('required') && $(this).val() == '') {
                        $(this).css('border-color', '#F44336');
                    }
                    //alert('Type: ' + $(this).attr('type') + 'Name: ' + $(this).attr('name') + 'Value: ' + $(this).val());
                }
            );
            $('input, textarea,redio,checkbox').blur(
                function (index) {
                    if ($(this).val() == '' && $(this).attr('required')) {
                        $(this).css('border-color', '#F44336');
                    } else {
                        $(this).css('border-color', '#d2d6de');
                    }
                }
            );
            $('select').each(
                function (index) {
                    //   var input = $(this);
                    if ($(this).attr('required')) {
                        $(this).css('border-color', '#F44336');
                    }
                    //alert('Type: ' + $(this).attr('type') + 'Name: ' + $(this).attr('name') + 'Value: ' + $(this).val());
                }
            );
            $('select').change(
                function (index) {
                    //   var input = $(this);
                    if ($(this).val() == '' && $(this).attr('required')) {
                        $(this).css('border-color', '#F44336');
                    } else {
                        $(this).css('border-color', '#d2d6de');
                    }
                    //alert('Type: ' + $(this).attr('type') + 'Name: ' + $(this).attr('name') + 'Value: ' + $(this).val());
                }
            );
            setTimeout(function () {
                $('iframe').css('display', 'none');
            }, 1000);

        });
        $(document).on('each blur change keypress keyup', '.required', function (event) {
            if ($(this).val() == '') {
                $(this).css('border-color', '#F44336');
            } else {
                $(this).css('border-color', '#d2d6de');
            }
        })
        $(document).on('input', '.cap', function (event) {
            this.value = this.value.toUpperCase();
        })
        $(document).on('input', '.num', function (event) {
            this.value = this.value.replace(/[^0-9]+/, '');
        })
        $(document).on('input', '.num-alfa', function (event) {
            this.value = this.value.replace(/[^A-Za-z0-9 _]+/, '');
        })
        $(document).on('input', '.alfa', function (event) {
            this.value = this.value.replace(/[^A-Za-z _]+/, '');
        })
        $(document).on('input', '.only-num-alfa', function (event) {
            this.value = this.value.replace(/[^A-Za-z0-9]+/, '');
        })
        $(document).on('input', '.float', function (event) {
            this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');
        });

        function properCase(string) {
            str = string.toLowerCase().replace(/\b[a-z]/g, function (letter) {
                return letter.toUpperCase();
            });
            return str;
        }

        function danger(text = '') {
            //alert();
            setTimeout(function () {
                $('#js-flash-message-box').fadeOut('slow');
            }, 5000);
            setTimeout(function () {
                $('#js-flash-message-box').remove();
            }, 6000);
            return '<div id="js-flash-message-box" class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><h4><i class="icon fa fa-ban"></i>' +
                text + '</h4></div>';
        }

        function success(text = '') {
            //alert();
            setTimeout(function () {
                $('#js-flash-message-box').fadeOut('slow');
            }, 5000);
            setTimeout(function () {
                $('#js-flash-message-box').remove();
            }, 6000);
            return '<div id="js-flash-message-box" class="alert alert-success alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><h4><i class="icon fa fa-check"></i>' +
                text + '</h4></div>';
        }

        function getDateInPart(date = null) {
            // date format -> "12-10-2020" //date.split("-").reverse().join("-")
            var currentTime = date == null ? new Date() : new Date(date);
            // returns the month (from 0 to 11)
            var month = currentTime.getMonth() + 1;
            // returns the day of the month (from 1 to 31)
            var day = currentTime.getDate()
            // returns the year (four digits)
            var year = currentTime.getFullYear();
            var yy = year.toString().substr(-2);
            return {
                "dd": day,
                "mm": month,
                "yyyy": year,
                "yy": yy
            };
        }
        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September",
            "October", "November", "December"
        ];

    </script>
</body>

</html>
