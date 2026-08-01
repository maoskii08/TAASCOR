var ExcelImport = function(params){

  check_required_libs();
  let X = XLSX;
  const maxWorkbookRows = Math.min(Number(params.maxWorkbookRows || 1000), 1000);
  const maxWorkbookBytes = Math.min(Number(params.maxWorkbookBytes || 1048576), 1048576);
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
  let importAuditContext = null;

  $(fileChooserSelector).on("change", function () {

      //obtain the file object
      let file = $(fileChooserSelector).prop("files")[0];
      if(!file) {
          alerter("No File Selected!");
          return false;
      }
      if (file.size > maxWorkbookBytes) {
          alerter('The selected file exceeds the 1 MiB atomic upload limit.');
          $(fileChooserSelector).val('');
          return false;
      }

      if(!verifyFile(file)) {
          alerter("Invalid File Selected!");
          document.getElementById('fileUploader').value= null;
          return false;
      }

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
      };

      $("#smx_progress-parsing").html("Reading Data From File . . .");

      //read it using the FileReader as a Binary
      reader.readAsBinaryString(file);

  });


  $(document).on("click", "#smx_downloadErrorDataExcelBt", function() {
      downloadErrorData();
  });

  function importButtonClick() {
      const changeReason = String($('#import-change-reason').val() || '').trim();
      const evidenceReference = String($('#import-evidence-reference').val() || '').trim();
      if (payrollDetails.length !== 1) {
          alerter('Select a client and payroll period before uploading additions.');
          return false;
      }
      if (changeReason.length < 5 || evidenceReference.length < 3) {
          alerter('Enter a business reason and an approval, ticket, or source-file reference before uploading.');
          return false;
      }

      columnMap = prepareColumnMap();

        if(!columnMap) {
            return false;
        }

        importAuditContext = {
            change_reason: changeReason,
            evidence_reference: evidenceReference,
            source_filename: fileName
        };
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
          alerter("Error Saving Excel File Locally");
      }
  }

  function setDefaults() {
      X = XLSX;
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
      const workbookRows = data.slice(1);
      if (workbookRows.length < 1 || workbookRows.length > maxWorkbookRows) {
          alerter('The workbook must contain between 1 and ' + maxWorkbookRows + ' adjustment rows. No rows were uploaded.');
          return false;
      }
      data = workbookRows;

      $(tableOutputSelector).append("<br>" +
          "<div id='smx_upload-block' class='form-group'>" +
          "<label>Uploading and validating the complete workbook</label><br>" +
          "<div id='progress' class='progress' style='height:20px;'>"+
          "<div id='smx_progress-upload' class='progress-bar progress-bar-striped bg-warning active' role='progressbar'"+
          "aria-valuenow='0' aria-valuemin='0' aria-valuemax='100' style='width:3%'>"+
          "</div></div></div>" +
          "<br><br>");

      $(fileChooserSelector + ", " + importTypeSelector + ", #smx_finalizeBt, .smx_col-maps").attr("disabled", "disabled");

      pushDataToServer(data);
      return true;
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

  function pushDataToServer(data) {
      let url = $(importTypeSelector).val();

      let payload = $.extend(
        {
          upload_mode: 'atomic-v1',
          expected_row_count: data.length,
          column_map: JSON.parse(columnMap),
          data: data,
          payrollDetails: payrollDetails
        },
        importAuditContext || {}
      );
      const requestBody = JSON.stringify(payload);
      if (new Blob([requestBody]).size > maxWorkbookBytes) {
          alerter('The workbook exceeds the 1 MiB atomic upload limit. No rows were uploaded.');
          $(fileChooserSelector + ", " + importTypeSelector + ", #smx_finalizeBt, .smx_col-maps").prop("disabled", false);
          return false;
      }

      $.ajax({
        url: url,
        type: "POST",
        contentType: "application/json;charset=utf-8",
        data: requestBody,
        dataType: "json",
        beforeSend: function () {
          $("#smx_progress-upload").css("width", "50%").html("Saving and recalculating...");
        }
      })
      .done(function (response) {
        if(response.success){
          $("#smx_progress-upload")
            .css("width", "100%")
            .removeClass("progress-bar-striped bg-warning active")
            .addClass("bg-success")
            .html("100%");
          $("#smx_upload-block label").html("Complete workbook committed");
          setDefaults();
          $('#importModal').modal('hide');
          window.Swal.fire({
            icon: 'success',
            title: 'Payroll additions uploaded',
            html: adjustmentAuditHtml(
              response,
              data.length + ' rows were validated, saved, audited, and recalculated as one transaction.'
            )
          }).then(function () {
            getAdditionalList();
          });
        }else{
          window.Swal.fire({
            icon: 'error',
            title: 'Addition upload stopped',
            text: responseError(response, 'The complete workbook was rejected and no adjustment rows were applied.')
          });
          $("#smx_progress-upload").removeClass("bg-warning active").addClass("bg-danger").html("Upload stopped");
        }
      })
      .fail(function (xhr) {
          window.Swal.fire({
              icon: 'error',
              title: 'Addition upload stopped',
              text: responseError(xhr, 'The workbook could not be confirmed. Validation and recalculation failures are rolled back; if the connection dropped after completion, review the filtered population before retrying.')
          });
          $("#smx_progress-upload").removeClass("bg-warning active").addClass("bg-danger").html("Upload stopped");
      });
      return true;
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
