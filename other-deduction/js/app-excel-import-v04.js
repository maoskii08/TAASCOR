var ExcelImport = function(params){

  check_required_libs();
  let X = XLSX;
  let batch = 0; //the current batch
  let groups = 0; //total number of batches
  let maxInAGroup = params.maxInAGroup || 1000;
  let start = 0; //indicate the start of the slice of the whole data
  let stop = maxInAGroup; //the stop index for the slice of the whole data for a batch
  let errorArray = [];
  let columnHeaders = [];
  let data = [];
  let fileName = "";
  let serverColumnNames = params.serverColumnNames;
  let extraData = params.extraData.obj || {};
  let columnMap = "";
  let importTypeSelector = params.importTypeSelector || "#dataType";
  let fileChooserSelector = params.fileChooserSelector || "#fileUploader";
  let tableOutputSelector = params.outputSelector || "#tableOutput";
  let importID = null;
  let date_array = [];

  $(fileChooserSelector).on("change", function () {

      //obtain the file object
      let file = $(fileChooserSelector).prop("files")[0];
      if(!file) {
          alerter("No File Selected!");
          return false;
      }

      if(!verifyFile(file)) {
          alerter("Invalid File Selected!");
          document.getElementById('fileUploader').value= null;
          return false;
      }

      _fileName = getFilename(file);

      $(tableOutputSelector).html("");
      if($(tableOutputSelector + " #smx_progress-block").length <= 0) {

          $("#readingFileStatus").html("" +
              "<div id='smx_progress-block' class='form-group'>" +
              "<div id='lblStatus'>Reading file and mapping required columns. Please wait... <i id='spinner' class='fa fa fa-spinner fa-spin'></i> </div>" +
              "<div class='progress'>" +
              "<div id='smx_progress-parsing' class='progress-bar progress-bar-striped active bg-success' role='progressbar' aria-valuenow='100' aria-valuemin='0' aria-valuemax='100' style='width:100%'></div>" +
              "</div></div></div>"
          );

      }

      let rABS = true; //readAsBinaryString
      let reader = new FileReader();

      fileName = file.name;

      reader.onload = function (e) {

          //get the binary data
          let data = e.target.result;
        
          //parse the data as a workbook
          try {
              let wb = X.read(data, {type: rABS ? 'binary' : 'array'});
              $('#fileUploader').prop('disabled', true);
              //update progress
              $("#smx_progress-parsing").removeClass("progress-bar-animated");
              $("#smx_progress-parsing").html("Reading Data From File Completed Successfully");
              $("#smx_progress-parsing").removeClass("active");
              $("#smx_progress-parsing").removeClass("progress-bar-striped");
              $("#smx_progress-parsing").removeClass("bg-success");
              $("#smx_progress-parsing").addClass("progress-bar-animated");
              $("#smx_progress-parsing").addClass("bg-primary");

              $("#spinner").fadeOut();
              $("#lblStatus").html('');
              //process the workbook
              processWorkbookData(wb);

          } catch(e) {
              console.log(e);
              alerter("Error Reading/Processing Excel File! Please Try again");
              $('#readingFileStatus').html("");
              $('#tableOutput').html("");
              $('#dataType').prop('disabled', false);
              $('#fileUploader').prop('disabled', false);
              document.getElementById('fileUploader').value= null;
              return false;
          }
      };

      reader.onerror = function (error) {
          $("#smx_progress-parsing").addClass("progress-bar-danger");
          $("#smx_progress-parsing").removeClass("active");
          $("#smx_progress-parsing").html("ERROR reading the File!");
          alerter("Error Parsing the Selected File!");
          $('#readingFileStatus').html("");
          $('#tableOutput').html("");
          $('#dataType').prop('disabled', false);
          $('#fileUploader').prop('disabled', false);
          document.getElementById('fileUploader').value= null;
          console.log(error);
      };

      $("#smx_progress-parsing").html("Reading Data From File . . .");

      //read it using the FileReader as a Binary
      reader.readAsBinaryString(file);

  });


  $(document).on("click", "#smx_downloadErrorDataExcelBt", function() {
      downloadErrorData();
  });

  function importButtonClick() {
      columnMap = prepareColumnMap();

        if(!columnMap) {
            error = 1;
            return false;
        }

        initUpload();

        $('#smx_finalizeBt').html('Upload');
  }

  $(document).on("click", "#smx_redoBt", function () {
      window.location.reload();
  });


  function displayErrorData() {

      if(errorArray.length > 0) {
          $(tableOutputSelector).append("<p style='color:red;'>There are " + errorArray.length + " records with error. " +
              "These are most likely data with duplicated entries." +
              " Click the red button below to download those data as excel<br><br>");
          $(tableOutputSelector).append("<button style='margin-right: 30px;' id='smx_downloadErrorDataExcelBt' " +
              "class='btn btn-danger btn-large btn-fill'>Download Error Data</button>");
      }
  }

  function errorDataToHtmlTable() {
      let tableStr = "<table class='errorTable'><tbody>";
      for(let i = 0; i < errorArray.length; i++) {
          let td = "";
          if(Array.isArray(errorArray[i])) {
              for (let j = 0; j < errorArray[i].length; j++) {
                  td = td + "<td>" + errorArray[i][j] + "</td>";
              }
          } else {
              td = td + "<td>" + errorArray[i] + "</td>";
          }

          tableStr = tableStr + "<tr>" + td + "</tr>";
      }

      tableStr = tableStr + "</tbody></table>";
      $(tableOutputSelector).append(tableStr);
  }

  function downloadErrorData() {

      if(errorArray.length <= 0) {
          alerter("There's no Error to download");
          return false;
      }

      let new_ws = X.utils.json_to_sheet(errorArray, {skipHeader:true, raw: true});

      /* build workbook */
      let new_wb = X.utils.book_new();
      X.utils.book_append_sheet(new_wb, new_ws, 'Data with Errors');

      /* write file and trigger a download */
      let wbout = X.write(new_wb, {bookType:'xlsx', bookSST:true, type:'binary'});
      let fname = 'error_data.xlsx';

      try {
          saveAs(new Blob([s2ab(wbout)],{type:"application/octet-stream"}), fname);
      } catch(e) {
          console.log(e, wbout);
          alerter("Error Saving Excel File Locally");
      }
  }

  function setDefaults() {
      X = XLSX;
      batch = 0;
      groups = 0;
      start = 0;
      stop = maxInAGroup;
  }

  function verifyFile(file) {
      let extTemp = file.type.split(".");
      let fileType = extTemp[extTemp.length - 1];


      let nameTemp = file.name.split(".");
      let ext = nameTemp[nameTemp.length - 1];


      return (
        (fileType == "sheet" || fileType == "ms-excel" || fileType == "text/csv") && (ext == "xlsx" || ext == "xls" || ext == "csv")

        );

  }

  function getFilename(file) {

      return file.name;

  }

  function showColumnMapping(data) {

      let columnNames = FuzzySet();
      let dynamicOptions = "";

      serverColumnNames.forEach(function(element) {
          columnNames.add(element);
      });

      let dynamicTB = "<tr>";
      let columnMatch = file_data['columns'].length;

      let colFound = [];
      let colRequired = [];

      // To edit
      colRequired = file_data['columns'];

      let counter = 0;
      for(let i = 0; i < data[0].length; i++){
        if(i !== columnMatch){
          let excelColName = ''; 
          if(data[0][i] != null){
            excelColName = data[0][i].toLowerCase().replace(/\s+/g, ' ').trim(); 
          }
          
          if(file_data['date'].includes(data[0][i])){
            date_array.push(i)
          }

          for (let col = 0; col < colRequired.length; col++) {
              var colHeaders = colRequired[col].toLowerCase().trim();
              if(extraData[colHeaders].includes(excelColName)){ 
                excelColName = colHeaders;
                counter += 1;
                colFound.push(excelColName);
              } 
          }

          let result = columnNames.get(excelColName);
          if(result){
            if(result[0][0] == 1){
              let col = result[0][1];
              let eVal = col.toLowerCase()
              dynamicOptions = "<option value='" + eVal + "'>" + result[0][1] + "</option>";
            } else {
              dynamicOptions =  "<option value='-1'>Ignore</option>";
              counter += 1;
            }
  
          } else {
            dynamicOptions =  "<option value='-1'>Ignore</option>";
            counter += 1;
          }
  
          dynamicTB += "<td><select class='smx_col-maps' data-index='" + i + "'>" + dynamicOptions + "</select></td>";
        } else {
          counter += 1;
        }
          
      }
      dynamicTB +=  "</tr>";

      let rowCount = data.length - 1;
      if(rowCount > 5){
        rowCount = 5;
      }

      for (let index = 0; index < rowCount; index++) {
        dynamicTB += addRow(index)
      }


      //LAST ROW
      dynamicTB +=  "<tr>"; //style='background:#DCDCDC;'
      for(let i = 0; i < data[0].length; i++){
         let colValue = data[data.length - 1][i];
         if(colValue == '' || colValue == null){
          dynamicTB += "<td><span style='color:#333;font-size: 9px'>--LAST ROW--</span><br/></td>"
         }else{
          dynamicTB += "<td><span style='color:#333;font-size: 9px'>--LAST ROW--</span><br/>" + colValue + "</td>"
         }
         
      }
      dynamicTB +=  "</tr>";


      console.log("Required: " + columnMatch);
      console.log("Found: " + counter);

      if(counter != columnMatch){

        swal.fire("HEADER CONSISTENCY ERROR", " Please check excel column names and try again.", "warning").
        then((value) => {
          $('#readingFileStatus').html("");
          $('#tableOutput').html("");
          $('#dataType').prop('disabled', false);
          $('#fileUploader').prop('disabled', false);
          document.getElementById('fileUploader').value= null;
        });



      }else {


        $(tableOutputSelector).append("<br><table class='table table-bordered table-responsive' id='tblRawData' style='font-size:11px;' cellspacing='0' overflow-x: scroll;>" +
            // "<thead><tr><th>This Data On Excel</th><th>Represent</th><th>What?</th><tr></thead>" +
            "<tbody>" + dynamicTB
            + "</tbody></table>" +
            "=========================" +
            // new Date() +
            "<br>FileName: <span id='file-name'>" + fileName +
            "</span><br>Total No of Records: <span id='total-records'>" + (numberWithCommas(data.length - 1)) +
            "</span><br>=========================<br>" +
            "<p id='tableError' style='color:red;'></p>"
            + "<button id='smx_finalizeBt' class='btn btn-primary btn-large btn-fill'>Upload</button><br><br>"
        );

        $('html, body').animate({ scrollTop:  $(tableOutputSelector).offset().top + 100}, 'slow');
        document.getElementById('smx_finalizeBt').addEventListener("click", importButtonClick);
      }



  }

  function numberWithCommas(x) {
      return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  function prepareColumnMap() {
    
    let colMap = {};  // Use an object instead of a string
    let seenEntries = new Set(); // Track already mapped values
    let err = false;

    // Reset previous errors
    $(".smx_col-maps").css("border", "1px solid #a9a9a9");
    $("#tableError").html("");

    $(".smx_col-maps").each(function () {
        let entry = $(this).val();
        if (entry !== "-1") {
            if (seenEntries.has(entry)) { // Check for duplicates
                $(this).css("border", "2px solid red");
                $("#tableError").html("<strong>ERROR! You have mapped more than one Data to the same type. See the row highlighted in RED</strong>");
                alert("ERROR! You have mapped more than one Data to the same type. See the row highlighted in RED");
                err = true;
            } else {
                seenEntries.add(entry); // Store unique values
                colMap[entry] = $(this).data("index");
            }
        }
    });

    if (!err) {
        return JSON.stringify(colMap); // Convert object to JSON string
    } else {
        $(".error").css("border", "2px solid red");
        return false;
    }      
  }

  function initUpload() {

      //remove the headers
      data.splice(0,1);

      //get the result of the division
      //if there are remainders, add 1 to the division to care for them
      groups = parseInt(data.length / maxInAGroup) + ((data.length % maxInAGroup) > 0 ? 1 : 0);


      $(tableOutputSelector).append("<br>" +
          "<div id='smx_upload-block' class='form-group'>" +
          "<label>Uploading 1 of " + groups + "</label><br>" +
          "<div id='progress' class='progress' style='height:20px;'>"+
          "<div id='smx_progress-upload' class='progress-bar progress-bar-striped bg-warning active' role='progressbar'"+
          "aria-valuenow='0' aria-valuemin='0' aria-valuemax='100' style='width:3%'>"+
          "</div></div></div>" +
          "<br><br>");

      $(fileChooserSelector + ", " + importTypeSelector + ", #smx_finalizeBt, .smx_col-maps").attr("disabled", "disabled");

      //ignite the chain
      callPushDataToServer();

      // $('html, body').animate({ scrollTop:  $(tableOutputSelector).offset().top + 50}, 'slow');
  }

  //string to array buffer
  function s2ab(s) {
      var b = new ArrayBuffer(s.length), v = new Uint8Array(b);
      for (var i=0; i != s.length; ++i) v[i] = s.charCodeAt(i) & 0xFF;
      return b;
  }

  function check_required_libs () {
      if(!window.XLSX || !$ || !saveAs) {
          let error = "Missing Libs! \n jQuery " +
              "\nhttps://github.com/SheetJS/js-xlsx " +
              "\nhttp://purl.eligrey.com/github/FileSaver.js/blob/master/FileSaver.js" +
              "\n is required!";
          console.log(error);
          alert(error);
          return false;
      }
  }

  function processWorkbookData(wb) {

      //empty the error array
      errorArray = [];

      /* get worksheet */
      let ws = wb.Sheets[wb.SheetNames[0]];

      //export the worksheet data as json
      data = X.utils.sheet_to_json(ws, {header: 1, raw: true, blankrows:false});
      //extract the headers
      columnHeaders = data[0];
      if(columnHeaders.length <= 0) {
          alerter("The first row in the document is EMPTY. It should contain the Headers");

          $('#readingFileStatus').html("");
          $('#tableOutput').html("");
          $('#dataType').prop('disabled', false);
          $('#fileUploader').prop('disabled', false);
          document.getElementById('fileUploader').value= null;

          return false;
      }

      if(data.length <= 1 || data[1].length <= 0) {
          alerter("The Excel Sheet Seems to have no data");

          $('#readingFileStatus').html("");
          $('#tableOutput').html("");
          $('#dataType').prop('disabled', false);
          $('#fileUploader').prop('disabled', false);
          document.getElementById('fileUploader').value= null;

          return false;
      }

      //show Mapping
      showColumnMapping(data);

  }

  function callPushDataToServer() {

      if(batch >= groups) {
          //just in case, but this is handled in update_progress
          //We are done processing
          return false;
      }

      //extract the next batch from the whole data
//                console.log("Pushing batch " + batch + " to server | start = " + start + " stop = " + stop);
      let currentData = data.slice(start, stop);
      setTimeout(pushDataToServer(currentData), 1000);

      //increase the index for the next batch so we know where we are
      start = stop;
      stop = stop + maxInAGroup;
  }

  function pushDataToServer(data) {
      let url = $(importTypeSelector).val();

      let payload = $.extend({column_map: JSON.parse(columnMap), data: data}, {payrollDetails: payrollDetails});

      $.ajax({
        url: url,
        type: "POST",
        contentType: "application/json;charset=utf-8",
        data: JSON.stringify(payload),
        dataType: "json"
      })
      .done(function (response) {
        if(response.success){
          updateProgress();
        }else{
          swal.fire({
            icon: 'error',   
            title: 'Something went wrong!',                 
            text: "Error Message: " + response.error.message + ""               
          }).then(function (result) {
              window.location.reload()
          });
        }
        

      })
      .fail(function (error) {
          alerter("ERROR OCCURRED! " + JSON.stringify(error) + "<br>");
          $('#readingFileStatus').html("");
          $('#tableOutput').html("");
          $('#dataType').prop('disabled', false);
          $('#fileUploader').prop('disabled', false);
          document.getElementById('fileUploader').value= null;
          $('#importModal').modal('hide');
      });


      
  }

 

  function updateProgress() {
      //this method is called when the server returned a response
      //either success or failure
      //increase the batch
      batch = batch + 1;

      //calculate the width of the progress bar and percentage done
      //it is safe to do this here as batch starts from 0
      let width = parseInt((batch / groups) * 100);
      $("#smx_progress-upload").css("width", width + "%");
      $("#smx_upload-block label").html("Uploaded " + batch + " of " + groups);
      $("#smx_progress-upload").html(width + "%");

      if(batch >= groups) {

          //if the current batch is greater than or equal to the number of available groups
          //then we are done
          setDefaults();
          $('html, body').animate({ scrollTop:  $(tableOutputSelector).offset().top + 250}, 'slow');

          var formdata = new FormData();
          for(let i=0; i < payrollDetails.length; i++) {
            formdata.append("client_name", payrollDetails[i][0]);
            formdata.append("cut_off", payrollDetails[i][1]);
            formdata.append("pay_day", payrollDetails[i][2]);
          }
          $.ajax({
            
            url: 'controller/ImportController.php',
            type: "POST",
            contentType: false,
            processData: false,
            data: formdata,
            dataType: "json",
            beforeSend: function (xhr) {
              $("#smx_progress-upload").html('Finalizing..');
            }
          })
          .done(function (response) {
            $("#smx_progress-upload").removeClass("progress-bar-animated");
            $("#smx_progress-upload").removeClass("active");
            
            if(response.success == 1){
              $('#importModal').modal('hide');
              swal.fire({
                icon: 'success',   
                title: 'Successfully Uploaded DTR!'           
              }).then(function (result) {
                  $('#importModal').modal('hide');
                  getDeductionList();
              });
              
            }else{
                $('#importModal').modal('hide');
                alerter("ERROR OCCURRED! " + JSON.stringify("Please contact your sytem administrator") + "<br>");
                $('#readingFileStatus').html("");
                $('#tableOutput').html("");
                $('#dataType').prop('disabled', false);
                $('#fileUploader').prop('disabled', false);
                document.getElementById('fileUploader').value= null;
            }
          })
          .fail(function (error) {
              $('#importModal').modal('hide');
              alerter("ERROR OCCURRED! " + JSON.stringify(error) + "<br>");
              $('#readingFileStatus').html("");
              $('#tableOutput').html("");
              $('#dataType').prop('disabled', false);
              $('#fileUploader').prop('disabled', false);
              document.getElementById('fileUploader').value= null;
          });
      }
      else {
          //call the next guy in the queue
          callPushDataToServer();
      }
  }

  function removeDuplicate(value, index, array) {
    return array.indexOf(value) === index;
  }



  $(document).on("click", ".errors", function () {
    let nameFile = $(this).data('fn');
    appendErrorTable(this.id, nameFile);
  });

  function appendErrorTable(error_id, nameFile){
    var error_index = error_id.replace('error_','');
    headers = '';
    for(let i=0; file_data['columns'].length>i; i++)
    { 
        headers += '<th>';
        headers += file_data['columns'][i];
        headers += '</th>';
    }

    $('.errorTable').html('');
    $('.errorTable').html(`
    <table id="tbl_error" class="table table-hovered table-bordered" style="width: 100%; font-size:12px;">
      <thead>
        ${headers}
      </thead>
    </table>
    `);
    
    $('#tbl_error').DataTable({
      "bFilter": true, 
      'paging'        : true,
      'lengthChange'  : true,
      'order'         : [],
      'binfo'          : true,
      'autoWidth'     : true,
      "bAutoWidth"    : false,
      'scroller'      : true,
      'scrollCollapse': true,
      'sScrollX'      : true,
      'scrollX'       : true,
      'data'          : data,
      dom: 'Bfrtip',
      buttons: [
        { 
            extend: 'excel',
            className: 'btn btn-sm btn-secondary',
            title: null,
            text:'<i class="fa fa-download mr-1"></i>Export',
            filename: nameFile

        }],
      data      : errorArray[error_index],
    });



  }

  function alerter(message) {
      if(window.swal) {
          swal.fire("Alert!", message, "warning");
      } else {
          alert(message);
      }
  }
  // to edit
  function addRow(counter){
    let dynamicTB =  "<tr>";
    for(let i = 0; i < data[0].length; i++){
      let colValue = data[counter][i];
      if(colValue == '' || colValue == null){
        dynamicTB += "<td></td>";
      }else if(date_array.includes(i)){
        dynamicTB += "<td>" + getJsDateFromExcel(colValue) + "</td>";
      }else{
        dynamicTB += "<td>" + colValue + "</td>";
      }
    }
    dynamicTB +=  "</tr>";
    return dynamicTB;
  }

  function getJsDateFromExcel(excelDate) {

    if(isNumber(excelDate)){
      let current_datetime = new Date((excelDate - (25567 + 2))*86400*1000)
      return formatted_date =  (current_datetime.getMonth() + 1) + "/" + (current_datetime.getDate()) + "/" + current_datetime.getFullYear()
    }else {
      return excelDate;
    }
  }

  function isNumber(n) { return /^-?[\d.]+(?:e-?\d+)?$/.test(n); }

};
