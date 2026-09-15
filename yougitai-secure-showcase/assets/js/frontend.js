(function(){'use strict';
function setFolder(folder,open){folder.classList.toggle('is-open',open);var btn=folder.querySelector(':scope > .yougitai-folder-toggle');if(btn){btn.setAttribute('aria-expanded',open?'true':'false');}}
document.querySelectorAll('[data-yougitai-showcase]').forEach(function(el){
  el.classList.add('yougitai-ready');
  el.querySelectorAll('.yougitai-folder-toggle').forEach(function(btn){btn.addEventListener('click',function(){var folder=btn.closest('.yougitai-folder');setFolder(folder,!folder.classList.contains('is-open'));});});
  var expand=el.querySelector('[data-yougitai-tree-expand]');if(expand){expand.addEventListener('click',function(){el.querySelectorAll('.yougitai-folder').forEach(function(folder){setFolder(folder,true);});});}
  var collapse=el.querySelector('[data-yougitai-tree-collapse]');if(collapse){collapse.addEventListener('click',function(){el.querySelectorAll('.yougitai-folder').forEach(function(folder){setFolder(folder,false);});});}
});
})();
