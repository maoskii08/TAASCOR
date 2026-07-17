let selector = document.querySelectorAll("#side-ul li a");
let page;
let url_page = $('#url_page').val();



selector.forEach(link => {
    clean_link = window.location.href.replace('#', '');
    if (clean_link === link.href) {
        if (link.href.includes(url_page)) {
            link.parentNode.classList.add('active');
            let parentMenu = link.closest('.menu-item.master'); 
            if (parentMenu) {
                parentMenu.classList.add('active');             
                // parentMenu.classList.add('open');                 
            }

            let parentUL = link.closest('.menu-sub');
            if (parentUL) {
                parentUL.style.display = 'block'; 
            }
        }
    }
});

let active = document.getElementsByClassName("active");
page = active[0].value;

checkAccess();

function checkAccess() {
    var formdata = new FormData();
    formdata.append("page", page);
    $.ajax({
        url: '../restriction/controller/RestrictionController.php',
        data: formdata,
        type: 'POST',
        contentType: false,
        processData: false,
        dataType: 'json',
        success: function(response){
            // console.log(response);
            if (response != null) {

                if (response.data.hasAccess === false) {
                    location.replace(response['data']['url']);
                }else{
                    for (let i = 0; i < response.data.page_id.length; i++) {
                        var li_id =  "a" + response.data.page_id[i];
                        document.getElementById(li_id).style.display = "block";
                        // $("#" + li_id).show();
                    }
                }
                    
            }


        }

    });
}


