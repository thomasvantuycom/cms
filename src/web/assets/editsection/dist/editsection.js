!function(e){var t=e("#sites").children("tbody").children(),i=t.children("th:nth-child(2)").children(".lightswitch"),n=t.find(".single-homepage .checkbox");function a(){t.each((function(t){!function(e,t){var n=i.eq(t);if(n.length){if(!n.data("lightswitch").on)return void n.parent().nextAll("td").addClass("disabled").find("textarea,div.lightswitch,input").attr("tabindex","-1");n.parent().nextAll("td").removeClass("disabled").find("textarea,div.lightswitch,input").attr("tabindex","0")}var a=e.children(".single-homepage").find(".checkbox"),d=e.children(".single-uri");a.prop("checked")?d.addClass("disabled").find("textarea").attr("tabindex","-1"):d.removeClass("disabled").find("textarea").attr("tabindex","0")}(e(this),t)}))}i.on("change",a),n.on("change",a),Garnish.$doc.ready((function(){a()}))}(jQuery);
//# sourceMappingURL=editsection.js.map