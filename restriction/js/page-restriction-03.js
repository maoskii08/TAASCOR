let selector = document.querySelectorAll("#side-ul li a");
let page = null;
let url_page = $('#url_page').val();
let currentPath = window.location.pathname.replace(/\/+$/, '');
let currentHash = window.location.hash || '';
let matchedLink = null;

selector.forEach(link => {
    if (!link.href || link.href.indexOf('javascript:') === 0) {
        return;
    }
    let target = new URL(link.href, window.location.origin);
    let targetPath = target.pathname.replace(/\/+$/, '');
    if (targetPath === currentPath && (!matchedLink || target.hash === currentHash)) {
        matchedLink = link;
    }
});

if (matchedLink && matchedLink.href.includes(url_page)) {
    matchedLink.parentNode.classList.add('active');
    matchedLink.setAttribute('aria-current', 'page');
    let permissionItem = matchedLink.closest('li[id^="a"][value]');
    if (permissionItem) {
        page = permissionItem.value;
    }

    let currentItem = matchedLink.parentElement;
    let parentMenu = currentItem ? currentItem.parentElement.closest('.menu-item.master') : null;
    while (parentMenu) {
        parentMenu.classList.add('active', 'open');
        currentItem = parentMenu;
        parentMenu = currentItem.parentElement.closest('.menu-item.master');
    }
}

initializeCollapsibleModules();

if (page !== null) {
    checkAccess();
}

function initializeCollapsibleModules() {
    document.querySelectorAll('#side-ul .menu-item.master').forEach(function (menuItem, index) {
        let toggle = menuItem.querySelector(':scope > .menu-toggle');
        let submenu = menuItem.querySelector(':scope > .menu-sub');
        if (!toggle || !submenu) {
            return;
        }

        // The shared menu component owns visibility through the parent's `open`
        // class. Removing legacy inline display values lets selected modules
        // collapse normally while their active child remains highlighted.
        submenu.style.removeProperty('display');

        if (!submenu.id) {
            submenu.id = 'taascor-menu-sub-' + (menuItem.id || index);
        }
        toggle.setAttribute('aria-controls', submenu.id);

        let syncExpandedState = function () {
            toggle.setAttribute('aria-expanded', menuItem.classList.contains('open') ? 'true' : 'false');
        };
        syncExpandedState();

        new MutationObserver(syncExpandedState).observe(menuItem, {
            attributes: true,
            attributeFilter: ['class']
        });
    });
}

function checkAccess() {
    var formdata = new FormData();
    formdata.append("page", page);
    formdata.append("csrf_token", $('#csrf_token').val() || '');
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
                        var menuItem = document.getElementById(li_id);
                        if (menuItem && menuItem.dataset.secondaryNavigation !== 'true') {
                            menuItem.style.display = "block";
                        }
                        // $("#" + li_id).show();
                    }
                }
                    
            }


        }

    });
}
