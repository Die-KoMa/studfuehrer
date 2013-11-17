scrolltableTopHeight = 0;
scrolltableLeftWidth = 0;

function fixedHeader(table_id) {
    var table = $('#' + table_id);
    // {{{ split up table into four parts
    var outerdiv = $('<div class="scrollable_outer"><table><tr><td><div class="scrollable_inner scrollable_top_left"><table><tr></tr></table></div></td><td><div class="scrollable_inner scrollable_top_right"><table></table></div></td></tr><tr><td><div class="scrollable_inner scrollable_bottom_left"><table></table></div></td><td><div class="scrollable_inner scrollable_bottom_right"><table></table></div></td></tr>');
    var topleft = outerdiv.find('.scrollable_top_left');
    var topright = outerdiv.find('.scrollable_top_right');
    var bottomleft = outerdiv.find('.scrollable_bottom_left');
    var bottomright = outerdiv.find('.scrollable_bottom_right');
    topleft.find('tr').append(table.find('thead th:eq(0)'));
    topright.find('table').append(table.find('thead'));
    var datarows = table.get(0).rows.length;
    var bottomlefttable = bottomleft.find('table');
    for(var i = 0; i < datarows; i++) {
        bottomlefttable.append($('<tr>').append(table.find('tbody tr:eq(' + i + ') td:eq(0)')));
    }
    bottomright.find('table').append(table.find('tbody'));
    table.replaceWith(outerdiv);
    // }}}

    scrolltableLeftWidth = parseInt(bottomleft.css('width'));
    scrolltableTopHeight = parseInt(topright.css('height'));

    resizeTable(outerdiv);

    // set scroll-synchronising Event handler
    bottomright.scroll(
        function() {
            topright.scrollLeft(bottomright.scrollLeft());
            bottomleft.scrollTop(bottomright.scrollTop());
        }
    );

    // set resizing Event handler
    $(window).resize(function() {
        resizeTable(outerdiv);
    });
}

function resizeTable(table) {
    // calculate width of scrollbar
    var a,b,c;
    if(c===undefined) {
        a = $('<div style="width:50px;height:50px;overflow:auto"><div/></div>').appendTo('body');
        b = a.children();
        c = b.innerWidth()-b.height(99).innerWidth();
        a.remove();
    }
    var wscroll = c;

    var topright = table.find('.scrollable_top_right');
    var bottomleft = table.find('.scrollable_bottom_left');
    var bottomright = table.find('.scrollable_bottom_right');
    var rightwidth = (parseInt(window.innerWidth) - scrolltableLeftWidth)-wscroll-2;
    topright.css('width', (rightwidth-wscroll)+'px');
    bottomright.css('width', rightwidth);
    var bottomheight = (parseInt(window.innerHeight) - scrolltableTopHeight)-50-2;
    bottomleft.css('height', (bottomheight-wscroll)+'px');
    bottomright.css('height', bottomheight+'px');

    bottomleft.css('margin-bottom', wscroll + 'px');
    topright.css('margin-right', wscroll + 'px');

    var datarows = bottomright.find('table tbody').get(0).rows;
    for(var i = 0; i < datarows.length; i++) {
        var rowcap = bottomleft.find('table tr:eq(' + i + ')');
        var datacell = bottomright.find('table tr:eq(' + i + ')');
        var height = Math.max(parseInt(rowcap.css('height')), parseInt(datacell.css('height')));
        rowcap.css('height', height + 'px');
        datacell.css('height', height + 'px');
    }
}
