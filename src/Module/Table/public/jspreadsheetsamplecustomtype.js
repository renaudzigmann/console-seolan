// jspreadsheet custom type searchtemplate
// custom type
(function(){
  var customTypeTest1Column = function() {
    var methods={};
    // pas appellée dans cette version (vient de la doc v8)
    methods.get=function(options, value){
      return value;
    };
    // Methods
    methods.closeEditor=function(cell, save) {
      var inputElement = cell.children[0];
      if (save){  // false si escape
  var value = inputElement.value; // le input
  cell.innerHTML = `<span data-value='${value}'>val = ${value}</val>`;
  return value;
      }
      inputElement.onblur=null;
    };
    methods.openEditor=function(cell, instance) {
      // Create input
      var element = document.createElement('input');
      element.style.border='1px inset blue';
      var that = this;
      var thatcell = cell;
      element.onblur = function(event){
  instance.jspreadsheet.closeEditor(thatcell, true);
      }

      element.value = cell.children[0].dataset.value;
      // Update cell
      cell.classList.add('editor');
      cell.innerHTML = '';
      cell.appendChild(element);
      // Focus on the element
      element.focus();
      element.onkeypress = function(e){
      }
    };
    // pas appelé ?
    methods.getValue=function(cell) {
      return cell.innerHTML;
    };
    // pas appelé ?
    methods.setValue=function(cell, value) {
      cell.innerHTML = value;
    };
    return methods;

  }();
