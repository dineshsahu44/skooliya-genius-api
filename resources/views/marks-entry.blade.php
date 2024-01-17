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
            padding: 3px;
            background-color: #ffabab;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 10px;
            line-height: 1.2em;
        }
        .till-due-panel {
            padding: 3px;
            background-color: #ff8787;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 10px;
            line-height: 1.2em;
        }
        .total-paid-panel {
            padding: 3px;
            background-color: #93ff95;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 10px;
            line-height: 1.2em;
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

        .table-thead-sticky{
            position: sticky;top: 0;
            background-color: #f1f1f1;
            z-index: 2;
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

<!-- Bootstrap Toggle CSS -->
<link rel="stylesheet" href="https://gitcdn.github.io/bootstrap-toggle/2.2.2/css/bootstrap-toggle.min.css">
<!-- filter css and js -->
<link rel="stylesheet" type="text/css" href="https://web.skooliya.com/Excel-like-Bootstrap-Table-Sorting-Filtering-Plugin/src/excel-bootstrap-table-filter-style.css"/>
<script type="text/javascript" src="https://web.skooliya.com/Excel-like-Bootstrap-Table-Sorting-Filtering-Plugin/dist/excel-bootstrap-table-filter-bundle.js"></script>
<script>
    function setSection(classid,sectionid,allclasswithsection){
        var allclasswithsection = JSON.parse(allclasswithsection);
        // console.log(allclasswithsection);
        classid = "#"+classid;
        sectionid = "#"+sectionid;
        $(sectionid).children('option').remove();
        $(sectionid).append(new Option("Select Section", "")).change();
        // alert($(classid).val());
        if($(classid).val()!='' && $(classid).val()!='All'){
            // console.log(allclasswithsection.find(record => record.id === 20),$(classid).val())
            $.each(allclasswithsection.find( record => record.id == $(classid).val()).section, function(i, sec) {
                // console.log(sec)
                // if(i==0){
                //     $(sectionid).append(new Option('All', 'All')).change();
                // }
                // $(sectionid).append(new Option(properCase(sec), sec)).change();
                $(sectionid).append(new Option(sec.section,sec.id)).change();
            
            });
        }
        // else if($(classid).val()=='All'){
        //     $(sectionid).append(new Option('All', 'All')).change();
        // }
    }
</script>
</head>

<body class="skin-red sidebar-mini fixed">
    <section class="content">
        <form action="#" method="post" id="markEntryForm">
            <div class="row" style="margin-bottom: 10px;">
                <div class="col-xs-6">
                    <select id="class" name="class" class="form-control"  onchange="setSection('class','section','{{json_encode($assigned_class['permittedclass'])}}')" required>
                        <option value="" selected>Select Class</option>
                        <!-- <option value="All">All</option> -->
                        @foreach($assigned_class['permittedclass'] as $class)
                            <option value="{{ $class['id'] }}">{{ $class['class'] }}</option>
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
                <div class="col-xs-6">
                    <select id="term" name="term" class="form-control" required>
                        <option value="" selected>Select Term</option>
                    </select>
                </div>
                <div class="col-xs-6">
                    <select id="assessment" name="assessment" class="form-control" required>
                        <option value="" selected disabled>Select Assessment</option>
                    </select>
                </div>
            </div>
            <div class="row" style="margin-bottom: 10px;">
                <div class="col-xs-6">
                    <select id="subject" name="subject" class="form-control" required>
                        <option value="" selected>Select Subject</option>
                    </select>
                </div>
                <div class="col-xs-6">
                    <button type="submit" class="col-xs-12 btn btn-info">Get Record</button>
                </div>
            </div>
            <!-- <div class="row" style="margin-bottom: 10px;">
                <div class="col-xs-12">
                    <button type="submit" class="col-xs-12 btn btn-info">Get Record</button>
                </div>
            </div> -->
        </form>
        <div class="row">
            <div class="col-xs-3" style="padding-right: 2px;">
                <div class="total-due-panel" style="background-color: #ace5ff8c;">
                    <div class="f-s-17 f-w-5">Total</div>
                    <div class="f-s-18 f-w-6" id="TotalStudent_Text">0</div>
                </div>
            </div>
            <div class="col-xs-3" style="padding-right: 2px;padding-left: 2px;">
                <div class="total-paid-panel">
                    <div class="f-s-17 f-w-5">Marked</div>
                    <div class="f-s-18 f-w-6" id="TotalCompleted_Text">0</div>
                </div>
            </div>
            <div class="col-xs-3" style="padding-left: 2px;padding-right: 2px;">
                <div class="total-due-panel">
                    <div class="f-s-17 f-w-5">Unmarked</div>
                    <div class="f-s-18 f-w-6" id="TotalUncompleted_Text">0</div>
                </div>
            </div>
            <div class="col-xs-3" style="padding-left: 2px;">
                <div class="total-due-panel" style="background-color: #f57461;">
                    <div class="f-s-17 f-w-5">Absent</div>
                    <div class="f-s-18 f-w-6" id="AbsentCount_Text">0</div>
                </div>
            </div>
        </div>
        <div class="row" style="max-height: 100vh;overflow: auto;">
            <div class="col-md-12">
                <!--  style="text-wrap: nowrap;width: 100%;max-height: 350px;min-height: 350px;overflow: auto;display: block;" -->
                <table class="table table-bordered table-hover" id="marksEnteryClassWiseTable">
                    <thead class="table-thead-sticky" id="marksEnteryClassWiseThead">
                    </thead>
                    <tbody id="marksEnteryClassWiseTableBody" style="font-weight: 600;">
                    </tbody>
                    <tfoot id="marksEnteryClassWiseTfoot">
                    </tfoot>
                </table>
            </div>
            <div class="col-md-12">
                <button type="button" style="width:100%;display:none;" class="btn btn-success" id="savemarks">Save Marks</button>
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
        
        var termAssessmentjson = '<?php echo $termAssessment; ?>';
	    var parsedTermAssessment = JSON.parse(termAssessmentjson);
        var mapedSubject = '<?php echo json_encode($mapedSubject); ?>';
	    var parsedmapedSubject = JSON.parse(mapedSubject);
        var pass_marks = '';
        var max_marks = '';
        // termAssessment = JSON.parse(termAssessment);
        $(document).on('change','#section',function () {
            var selected_section = $(this).val();
            var selected_class = $('#class').val();
            // $("#subjectmarks").children('option').remove();
            $("#subject").html('').append(new Option("Select Subject", "")).change();
            $('#term').html('').append(new Option("Select Term", "")).change();
            assesment_value = [];
            if(selected_class==''||selected_section==''){
                return;
            }

            map_subject_value = selected_section?parsedmapedSubject[selected_section]:undefined;
            map_termAssessment = selected_section?parsedTermAssessment[selected_section]:undefined;
            if(map_subject_value){
                // Select your target select element
                var selectElement = $('#subject');
                selectElement.html('');
                // Add an initial option
                selectElement.append('<option value="">Select Subject</option>');

                // Loop through the data and create optgroup and options
                map_subject_value.GroupDetails.forEach(function (group) {
                    var optgroup = $('<optgroup>').attr('label', group.SubjectGroup.GroupName);

                    group.Subjects.forEach(function (subject) {
                        var option = $('<option>').attr('value', subject.SubjectID).text(subject.SubjectName);
                        optgroup.append(option);
                    });

                    selectElement.append(optgroup);
                });
            }

            if(map_termAssessment){
                var selectElement = $('#term');
                termoption = '<option value="">Select Term</option>';
                $.each(map_termAssessment, function( key, value ) {
                    termoption = termoption +'<option value="'+value.Term.id+'" data-assessment='+encodeURIComponent(JSON.stringify(value.Assessment))+'>'+value.Term.term+'</option>';
                });
                selectElement.html(termoption).change();
            }
        });

        $(document).on('change','#term',function(){
            assoption = '<option value="">Select Assessment</option>';		
            if(this.value!==''){
                var selectedOption = $(this).find(':selected');
                var dataAssessment = JSON.parse(decodeURIComponent(selectedOption.data('assessment')));
                
                $.each(dataAssessment, function( key, value ) {
                    assoption = assoption +'<option value="'+value.AssessmentID+'">'+value.AssessmentName+'</option>';
                });
            }
            $('#assessment').html(assoption).change();
        });

        $('#markEntryForm').on('submit',function(e){
            e.preventDefault();
            $.ajax({
                type: 'POST',
				url:   window.location.href,
				data: $(this).serialize()+'&operation=get',
				dataType: 'json',
				beforeSend: function (xhr) {
                    xhr.setRequestHeader('Authorization', "{{$auth_token}}");
                },
                complete: function(data) {
                    // $(".jconfirm-content-pane").LoadingOverlay("hide");
                },
                success: function(data) {
                    drawStudentmarksTable(data);
                },
            });
        });

        $("#savemarks").on("click", function () {
            const $tableRows = $("#marksEnteryClassWiseTableBody tr");

            const dataArray = [];

            // Iterate through each row (skip the header row with index 0)
            $tableRows.each(function () {
                const id = $(this).find(".obt-marks").attr("data-id");
                const appid = $(this).find(".obt-marks").attr("data-appid");
                const obtMarks = $(this).find(".obt-marks").text().trim();
                const remarks = $(this).find(".remarks").text().trim();
                const isAbsent = $(this).find(".is-attendance").prop("checked") ? "Yes" : "No";

                // Create an object with the extracted data
                const dataObject = {
                    "id": id,
                    "Appid": appid,
                    "ObtMarks": obtMarks,
                    "Remarks": remarks,
                    "IsAbsent": isAbsent,
                };

                // Add the object to the array
                dataArray.push(dataObject);
            });
            const data = {
                class: $('#class').val(),
                section: $('#section').val(),
                subject: $('#subject').val(),
                term: $('#term').val(),
                assessment: $('#assessment').val(),
                PassMarks: pass_marks,
                MaxMarks: max_marks,
                operation: 'save',
                studentRecord: dataArray,
            }
            
            if(dataArray.length>0){
                $.ajax({
                    type: 'POST',
                    url: window.location.href,
                    data: data,
                    dataType: 'json',
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('Authorization', "{{$auth_token}}");
                    },
                    complete: function(data) {
                        // $(".jconfirm-content-pane").LoadingOverlay("hide");
                    },
                    success: function(data) {
                        // console.log(data);
                        drawStudentmarksTable(data);
                    },
                });
            }

        });
        
        function drawStudentmarksTable(data){
            pass_marks = (typeof data.data.classSetting.MinMarks === 'undefined')?'':data.data.classSetting.MinMarks;
            max_marks = (typeof data.data.classSetting.MaxMarks === 'undefined')?'':data.data.classSetting.MaxMarks;
            // <td data-b-a-s="thin" data-t="n">`+data.data.classSetting.MaxMarks+`</td>
            // <td data-b-a-s="thin" data-t="n">`+data.data.classSetting.MinMarks+`</td>
            //  <th class="header apply-filter" width="5%" data-f-bold="true" data-b-a-s="thin">#</th>
            // <td data-b-a-s="thin" data-t="n"><b>`+(key+1)+`.</b></td>
            // <th class="header apply-filter" width="5%" data-f-bold="true" data-b-a-s="thin">AppID</th>
            // <td data-b-a-s="thin">`+value.registration_id+`</td>
            // <th class="header apply-filter" width="5%" data-f-bold="true" data-b-a-s="thin">AdmNo</th>
            // <td data-b-a-s="thin">`+value.scholar_id+`</td>
            
            $.alert(data.msg);
            if(data.success==1){
                var TotalStudent = 0;
                var TotalCompleted = 0;
                var TotalUncompleted = 0;
                var AbsentCount = 0;
                var thead = `
                    <tr class="bg-light-blue">
                        <th class="header apply-filter" width="5%" data-f-bold="true" data-b-a-s="thin">RollNo</th>
                        
                        <th class="header apply-filter" data-f-bold="true" data-b-a-s="thin">Student Name</th>
                        <th class="header" data-f-bold="true" data-b-a-s="thin" width="25%">
                            Marks Obt.<br>
                            `+pass_marks+` | `+max_marks+`
                        </th>
                        <th class="header" width="35%" data-f-bold="true" data-b-a-s="thin" style="display: none;">Remarks</th>
                        <th class="header" width="5%" data-f-bold="true" data-b-a-s="thin" style="display: none;">Absent</th>
                    </tr>
                `;
                var tablebody = '';
                var classSettingLength =Object.keys(data.data.classSetting).length;
                if(classSettingLength>0){
                    $.each(data.data.studentRecord, function(key, value ) {
                        tablebody = tablebody+`<tr>
                            <td data-b-a-s="thin" data-t="n">`+(value.roll_no==null?'':value.roll_no)+`</td>
                            
                            <td data-b-a-s="thin">`+value.name+`</td>
                            <td data-b-a-s="thin" data-t="n" class="editable-cell float obt-marks" inputmode="numeric" contenteditable="true" data-id="`+value.id+`" data-appid="`+value.registration_id+`">
                            `+value['ObtMarks']+`
                            </td>
                            <td data-b-a-s="thin" class="editable-cell cap remarks" contenteditable="true" style="display: none;">             
                            `+value['Remarks']+`
                            </td>
                            <td class="att-toggle-btn" data-b-a-s="thin" style="display: none;">
                                <input type="checkbox" class="attendance-toggle is-attendance" data-toggle="toggle" data-on="Yes" data-off="No" data-onstyle="success" data-offstyle="danger" data-size="mini" `+(value.IsAbsent=='Yes'?'checked':'')+`>
                            </td>
                        </tr>`;

                        TotalStudent++;
                        
                        if(value['ObtMarks']!=''){
                            TotalCompleted++;
                        }

                        if(value.IsAbsent=='Yes'){
                            AbsentCount++;
                        }
                    });
                }else{
                    msg = "First goto the exam configration and select Grading System. Now assign Pass and Max Marks!";
                    $.alert({type: 'orange',title:'Warning',content:msg});
                }
                TotalUncompleted = TotalStudent-TotalCompleted;
                if(Object.keys(data.data.studentRecord).length>0 && classSettingLength>0){
                    $('#savemarks').show();
                    // $('#deletemarks').show();
                }else{
                    $('#savemarks').hide();
                    // $('#deletemarks').hide();
                }
                
                $('#TotalStudent_Text').text(TotalStudent);
                $('#TotalCompleted_Text').text(TotalCompleted);
                $('#TotalUncompleted_Text').text(TotalUncompleted);
                $('#AbsentCount_Text').text(AbsentCount);
                // $('#marksEnteryClassWiseTfoot').html(tfoot);
                $('#marksEnteryClassWiseThead').html(thead);
                $('#marksEnteryClassWiseTableBody').html(tablebody);
                // showAndHideColumns('marksEnteryClassWiseTable');
                $('table').excelTableFilter({
                    columnSelector: '.apply-filter',  
                });
                $('.attendance-toggle').bootstrapToggle();
            }
        }
    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.31.3/js/jquery.tablesorter.min.js">
    </script>
    <script type="text/JavaScript" src="https://cdnjs.cloudflare.com/ajax/libs/jQuery.print/1.6.0/jQuery.print.js">
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.2/jquery-confirm.min.js"></script>

    <script src="https://gitcdn.github.io/bootstrap-toggle/2.2.2/js/bootstrap-toggle.min.js"></script>
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
        $(document).on('input', '.cap', function(event) {
            var cursorPosition = getCaretPosition(this);
            if (this.value !== undefined) {
                this.value = this.value.toUpperCase();
            } else {
                $(this).text($(this).text().toUpperCase());
            }
            setCaretPosition(this, cursorPosition);
        })
        $(document).on('input', '.num', function(event) { 
            var cursorPosition = getCaretPosition(this);
            if (this.value !== undefined) {
                this.value = this.value.replace(/[^0-9]+/, '');
            } else {
                $(this).text($(this).text().replace(/[^0-9]+/, ''));
            }
            setCaretPosition(this, cursorPosition);
        })
        $(document).on('input', '.num-alfa', function(event) { 
            var cursorPosition = getCaretPosition(this);
            if (this.value !== undefined) {
                this.value = this.value.replace(/[^A-Za-z0-9 _]+/, '');
            }else {
                $(this).text($(this).text().replace(/[^A-Za-z0-9 _]+/, ''));
            }
            setCaretPosition(this, cursorPosition);
        })
        $(document).on('input', '.alfa', function(event) { 
            var cursorPosition = getCaretPosition(this);
            if (this.value !== undefined) {
                this.value = this.value.replace(/[^A-Za-z _]+/, '');
            }else {
                $(this).text($(this).text().replace(/[^A-Za-z _]+/, ''));
            }
            setCaretPosition(this, cursorPosition);
        })
        $(document).on('input', '.only-num-alfa', function(event) {
            var cursorPosition = getCaretPosition(this);
            if (this.value !== undefined) { 
                this.value = this.value.replace(/[^A-Za-z0-9]+/, '');
            }else {
                $(this).text($(this).text().replace(/[^A-Za-z0-9]+/, ''));
            }
            setCaretPosition(this, cursorPosition);
        })
        $(document).on('input', '.float', function(event) {
            var cursorPosition = getCaretPosition(this);
            if (this.value !== undefined) {
                this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');
            } else {
                $(this).text($(this).text().replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1'));
            }
            setCaretPosition(this, cursorPosition);
        });
        
        $(document).on('keydown','.editable-cell:visible', function (e) {
            if ($(this).attr('contenteditable') === 'true') {
                // Your existing keydown event handling code here
                var keyCode = e.keyCode || e.which;

                var positionInRow = $(this).index();
                var $currentRow = $(this).closest('tr');
                
                // Handle down arrow key && Enter key
                if (keyCode === 13||keyCode === 40) {
                    var $nextRow = $currentRow.next('tr');

                    if($nextRow.length == 0){
                        $nextRow = $currentRow.closest('table').find('tbody tr').first();
                    }
                    
                    var $editableCells = $nextRow.find('.editable-cell:visible');
                    var numCells = $editableCells.length;
                    // Calculate the corrected index and wrap around
                    var correctedIndex = (positionInRow + 1) % numCells;
                    // If the corrected index is 0, move to the first cell in the next row
                    // Move to the cell in the next row
                    $editableCells.eq(correctedIndex).focus();
                    e.preventDefault(); // Prevent the default behavior of the Enter key
                }

                // Handle Up arrow key
                if (keyCode === 38) {
                    var $prevRow = $currentRow.prev('tr');

                    if ($prevRow.length > 0) {
                        var $editableCells = $prevRow.find('.editable-cell:visible');
                    } else {
                        // If there is no previous row, find the last row
                        var $lastRow = $currentRow.closest('table').find('tbody tr').last();
                        var $editableCells = $lastRow.find('.editable-cell:visible');
                    }
                    var numCells = $editableCells.length;
                    var correctedIndex = (positionInRow - 1 + numCells) % numCells;
                    // Move to the cell in the last row at the same position
                    $editableCells.eq(correctedIndex).focus();

                    e.preventDefault(); // Prevent the default behavior of the Up arrow key
                }

                // Handle Right arrow key
                // if (keyCode === 39) {
                //     var $currentCell = $('.editable-cell:focus');

                //     if ($currentCell.length > 0) {
                //         var $nextCell = $currentCell.next('.editable-cell');

                //         if ($nextCell.length > 0) {
                //         $nextCell.focus();
                //         e.preventDefault(); // Prevent the default behavior of the Right arrow key
                //         }
                //     }
                // }

                // Handle Left arrow key
                // if (keyCode === 37) {
                //     var $currentCell = $('.editable-cell:focus');

                //     if ($currentCell.length > 0) {
                //         var $prevCell = $currentCell.prev('.editable-cell');

                //         if ($prevCell.length > 0) {
                //             $prevCell.focus();
                //             e.preventDefault(); // Prevent the default behavior of the Left arrow key
                //         }
                //     }
                // }
            }
        });

        // Add event listener for focus event on "editable-cell" cells
        $(document).on('focus', '[contenteditable="true"]', function () {
            var textLength = this.textContent.length;
            // Set the cursor position to the end of the text
            setCaretToEnd(this, textLength);
        });

        // $(document).on('change', '.obt-marks [contenteditable="true"]', function () {
        //     alert($(this).text());
        // });

        $(document).on('blur', '.editable-cell.obt-marks[contenteditable="true"]', function () {
            var newText = $(this).text().trim();

            if (max_marks === '' && pass_marks === '') {
                $.alert('First fill MaxMarks and PassMarks!');
            } else {
                // Convert values to numbers
                newText = parseFloat(newText);
                max_marks = parseFloat(max_marks);
                pass_marks = parseFloat(pass_marks);

                // console.log(newText, max_marks, pass_marks);

                if (isNaN(newText) || isNaN(max_marks) || isNaN(pass_marks)) {
                    // Handle the case if any of the values are not valid numbers
                    // console.log('Invalid number(s) entered.');
                } else {
                    if (newText > max_marks) {
                        $.alert('ObtMarks is greater than MaxMarks');
                        $(this).text('');
                    } 
                    // else if (newText < pass_marks) {
                    //     $.alert('ObtMarks is less than PassMarks');
                    //     $(this).text('');
                    // }
                }
            }
        });

        // Function to set the cursor position to the end of the text
        function setCaretToEnd(element, position) {
            // Ensure the element is not null or undefined
            if (!element) {
                return;
            }

            // Check if the element has child nodes
            if (!element.firstChild) {
                // If there are no child nodes, append a text node
                element.appendChild(document.createTextNode(''));
            }

            // Create a new range
            var range = document.createRange();

            // Set the start and end of the range
            range.setStart(element.firstChild, position);
            range.collapse(true);

            // Create a new selection and set the range
            var selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        }

        // Function to get the cursor position
        function getCaretPosition(element) {
            var caretPosition = 0;
            var range = window.getSelection().getRangeAt(0);
            var preCaretRange = range.cloneRange();
            preCaretRange.selectNodeContents(element);
            preCaretRange.setEnd(range.endContainer, Math.min(range.endOffset, element.textContent.length));
            caretPosition = preCaretRange.toString().length;
            return caretPosition;
        }

        // Function to set the cursor position
        function setCaretPosition(element, position) {
            var range = document.createRange();
            var selection = window.getSelection();
            
            // Ensure the position is within the valid range
            position = Math.min(position, element.textContent.length);

            range.setStart(element.firstChild, position);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
        }

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
