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

        .success-list {
            background-color: #17ff4947;
            display: flex;
            align-items: center;
        }

        .danger-list {
            background-color: #ff171747;
            display: flex;
            align-items: center;
        }

        .circle-badge {
            margin-right: 15px;
            margin-left: 3px;
            width: 35px;
            height: 35px;
            line-height: 35px;
            border-radius: 50%;
            color: #fff;
            text-align: center;
            background: #f39c12;
            font-weight: 600;
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
            background-color: #E8ECEB;
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
        if($(classid).val()!='' && $(classid).val()!='All'){
            // console.log(allclasswithsection.find( record => record.class === $(classid).val()))
            $.each(allclasswithsection.find( record => record.class === $(classid).val()).section, function(i, sec) {
                
                if(i==0){
                    $(sectionid).append(new Option('All', 'All')).change();
                }
                // $(sectionid).append(new Option(properCase(sec), sec)).change();
                $(sectionid).append(new Option(sec, sec)).change();
            
            });
        }else if($(classid).val()=='All'){
            $(sectionid).append(new Option('All', 'All')).change();
        }
    }
</script>
</head>

<body class="skin-red sidebar-mini fixed">
    <section class="content">
        <form action="#" method="post" id="fee_day_book_form">
            <div class="row" style="margin-bottom: 10px;">
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
            </div>
            <div class="row" style="margin-bottom: 10px;">
                <div class="col-xs-6">
                    <select id="class" name="class" class="form-control"  onchange="setSection('class','section','{{json_encode($assigned_class['permittedclass'])}}')" required>
                        <option value="" selected>Select Class</option>
                        <option value="All">All</option>
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
            <div class="col-md-12">
                <div class="total-due-panel">
                    <div class="f-s-17 f-w-5">Total Collection</div>
                    <div class="f-s-25 f-w-6" id="total-collection">₹0</div>
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
                    // console.log(data);
                    // var payment=0;
                    var total_paid_amount = 0;
                    var list = '';
                    $.each(data,function(k,ph){
                        list += `<li class="special-list success-list"
                            data-amount-in-word="`+properCase(getIndianCurrency(ph['paid_amount']))+`"
                            data-receipt-details='`+JSON.stringify(ph)+`'
                            data-fees-details='`+ph["object"]+`'>
                                <div class="circle-badge">`+(k+1)+`</div>
                                <div class="flex-row">
                                    <div class="justify-between">
                                        <div>`+ph['ReceiptNo']+`</div>
                                        <div>`+properCase(ph['StudentName'])+`</div>
                                    </div>
                                    <div class="text-muted justify-between f-s-15">
                                        <div>`+ph['ReceiptDate']+`</div>
                                        <div>`+ph['Class']+` - `+ph['Section']+`</div>
                                        <div>`+(new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(ph['paid_amount']))+`</div>
                                    </div>
                                </div>
                            </li>`;
                        total_paid_amount +=ph['paid_amount'];
                        // console.log(ph['paid_amount'],total_paid_amount);
                    });
                    // console.log(total_paid_amount);
                    $('#total-collection').text(new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(total_paid_amount));
                    $('#fee-day-book-list').html(list);
                    $('.success-list').on('click', function(){
                        var fees_details = $(this).data('fees-details');
                        var receipt_details = $(this).data('receipt-details');
                        if (fees_details) {
                            var amount_in_word = $(this).data('amount-in-word');
                            var tbody = '';
                            const groupedData = {};
                            $.each(fees_details, function (index, item) {
                                const fhead = item.fhead;
                                const defultAmount = item.defult_amount;

                                if (!groupedData[fhead]) {
                                    groupedData[fhead] = {
                                        fhead: fhead,
                                        total_defult_amount: 0,
                                        items: []
                                    };
                                }

                                groupedData[fhead].total_defult_amount += defultAmount;
                                groupedData[fhead].items.push(item);
                            });

                            const groupedArray = Object.values(groupedData);
                            // console.log(groupedArray);

                            $.each(groupedArray, function (key, fees) {
                                tbody += '<tr><td>' + (key + 1) + '</td><td>' + fees['fhead'] +
                                    '</td><td style="text-align: right;">₹' +
                                    fees['total_defult_amount'] + '</td></tr>';
                            })

                            var printReceipt = `
                                <section class="invoice" style="padding: 0;margin: 0;">
                                    <div class="row">
                                        <div class="col-xs-12">
                                            <div class="row" style="border-bottom: 2px solid;margin-bottom: 5px; margin-right: 0px;">
                                                <!-- <div class="col-xs-2">
                                                    <img width="80" height="80" alt="School Logo" src="logo" onerror="this.onerror=null; this.src='base.'/img/altlogo.png';?>'">
                                                </div> -->
                                                <div class="col-xs-12 text-center">
                                                    
                                                </div>
                                            </div>
                                            <div class="row" style="border-bottom: 2px outset;margin-right: 0px;">
                                                <div class="col-xs-12">
                                                    <div style="display: flex;justify-content: space-between;">
                                                        <b>Receipt No. ` + receipt_details['ReceiptNo'] + `</b>
                                                        <span>App ID: <strong>` + receipt_details['AppID'] + `</strong></span>
                                                        <span>Date: <strong>` + receipt_details['ReceiptDate'] + `</strong></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row invoice-info">
                                                <div class="col-xs-5">
                                                    Class: <b>` + receipt_details['Class'] + ` - ` + receipt_details['Section'] + `</b><br>
                                                    Mobile: <b>` + receipt_details['mobile'] + `</b>
                                                </div>
                                                <div class="col-xs-7">
                                                    Name: <b>` + receipt_details['StudentName'] + `</b><br>
                                                    Father: <b>` + receipt_details['FatherName'] + `</b>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-xs-12">
                                                    Months:<b>` + (receipt_details['Months'] != null ? receipt_details['Months'] :
                                    '') + `</b>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-xs-12">
                                                    <table class="table table-striped table-condensed" style="margin-bottom: 0px;">
                                                        <thead>
                                                            <tr>
                                                                <th>SN</th>
                                                                <th>Particulars</th>
                                                                <th style="text-align: right;">Amount</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            ` + tbody + `
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-xs-5">
                                                <!-- class="table table-bordered table-condensed" -->
                                                    Deposited:<b><br>By Cash</b><br>
                                                    Total Amount (In Words)<br>
                                                    <b>` + amount_in_word + `</b>

                                                    <!-- Remark:getIndianCurrency() --> 
                                                </div>
                                                <div class="col-xs-7">
                                                    <div>
                                                        <table class="table table-condensed">
                                                            <tbody>
                                                                <tr>
                                                                    <th style="text-align: right;">Late Fine:</th>
                                                                    <td style="text-align: right;">+` + receipt_details['fine'] + `/-</td>
                                                                </tr>
                                                                <tr>
                                                                    <th style="text-align: right;">Concession:</th>
                                                                    <td style="text-align: right;">-` + receipt_details['discount'] + `/-</td>
                                                                </tr>
                                                                <tr>
                                                                    <th style="text-align: right;">Total:</th>
                                                                    <td style="text-align: right;font-size: 17px;">` +receipt_details['default_amount'] + `/-</td>
                                                                </tr>
                                                                <tr style="color:green;">
                                                                    <th style="text-align: right;">Paid Amount:</th>
                                                                    <th style="text-align: right;font-size: 18px;">` +receipt_details['paid_amount'] + `/-</tH>
                                                                </tr>
                                                                <tr style="color:red;">
                                                                    <th style="text-align: right;">Balance:</th>
                                                                    <td style="text-align: right;font-size: 16px;">` +receipt_details['due'] + `/-</td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </section>`;
                            var jc = $.confirm({
                                columnClass: 'medium',
                                title: false,
                                content: printReceipt,
                                buttons: {
                                    close: function () {
                                        //close
                                    },
                                },
                            });
                        }
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
        // const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September",
        //     "October", "November", "December"
        // ];

    </script>
</body>

</html>
