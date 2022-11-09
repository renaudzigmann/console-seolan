// Tag related functions

var tag_prefix = '#';

// delete a tag span and update hidden field value
function delete_tag(link, fname) {
    var hidden = document.getElementById(fname);
    var hvalue = hidden.value;
    var tvalue = tag_prefix+link.text+" ";
    hvalue = hvalue.replace(tvalue,'');
    var span = link.parentNode;
    var div = span.parentNode;
    div.removeChild(span);
    hidden.value = hvalue;
    return false;
}

// add a tag from a suggestion
function add_tag(id, tvalue, title, deleteOnExists=false) {
    var text = document.getElementById("_INPUT"+id);
    var hid = id.replace(/_text$/,"_hidden");
    var hidden = document.getElementById(hid);
    var pid = id.replace(/_text$/,"_preview");
    var div = document.getElementById(pid);
    var tvalue2 = tag_prefix+tvalue+" ";
    var lctvalue2 = tvalue2.toLowerCase();
    var exists = hidden.value.toLowerCase().match(lctvalue2);
    if (tvalue && !exists) {
        hidden.value += tvalue2;
        var span = document.createElement("span");
        span.className = "tag";
        span.innerHTML = '<a class="tag" onclick="return delete_tag(this, \''+hid+'\')" href="" title="'+title+'">'+tvalue+'</a>';
        div.appendChild(span);
    } else if (exists && deleteOnExists) {
        var tags = div.getElementsByClassName('tag');
        for (var i = 0; i<tags.length; i++) {
            if (tags[i].tagName.toLowerCase() == 'span') {
                if (tags[i].textContent == tvalue) {
                    hidden.value = hidden.value.replace(tvalue2,'');
                    tags[i].remove();
                    break;
                }
            }
        }
    }
    text.value='';
}

// set search tag value from a suggestion
function set_search_tag(id, tvalue) {
    var text = document.getElementById(id);
    var tvalue2 = tag_prefix+tvalue+" ";
    text.value = tvalue2;
}

// add a tag from a suggestion of a text or a textarea
function add_input_tag(id, tvalue, title, tid) {
    var text = document.getElementById("texttaginput");
    if (tid) {
        var hid = tid+"_hidden";
        var hidden = document.getElementById(hid);
        var pid = tid+"_preview";
        var div = document.getElementById(pid);
    }
    var tvalue2 = tvalue+" ";
    var lctvalue2 = tvalue2.toLowerCase();
    if (tid && tvalue && !hidden.value.toLowerCase().match(lctvalue2)) {
        hidden.value += tvalue2;
        var tvalue3 = tvalue.substring(1);
        var span = document.createElement("span");
        span.className = "tag";
        span.innerHTML = '<a class="tag" onclick="return delete_tag(this, \''+hid+'\')" href="" title="'+title+'">'+tvalue3+'</a>';
        div.appendChild(span);
    }
    var cke = CKEDITOR.instances[id];
    if (!cke) { // add from text or textarea
        var input = document.getElementById(id);
        jQuery("#"+id).insertAtCaret(tvalue);
        input.focus();
    } else { // add from CKEditor
        var ckeid = jQuery("#texttaginput").attr("ckeid");
        CKEDITOR.instances[id].document.$.getElementById(ckeid).innerHTML = tvalue;
        jQuery("#texttaginput").hide();
    }
}
