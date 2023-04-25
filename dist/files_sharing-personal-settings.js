/*! For license information please see files_sharing-personal-settings.js.LICENSE.txt */
(()=>{"use strict";var e,r={73168:(e,r,a)=>{var n=a(20144),s=a(45994),o=a(79753),i=a(79954),l=a(64024),c=a(4820),d=a(62520),p=a.n(d);const f=(0,i.j)("files_sharing","default_share_folder","/"),u=(0,i.j)("files_sharing","share_folder",f),h={name:"SelectShareFolderDialogue",data:()=>({directory:u,defaultDirectory:f}),computed:{readableDirectory(){return this.directory?this.directory:"/"}},methods:{async pickFolder(){const e=(0,l.fn)(t("files","Choose a default folder for accepted shares")).startAt(this.readableDirectory).setMultiSelect(!1).setModal(!0).setType(1).setMimeTypeFilter(["httpd/unix-directory"]).allowDirectories().build();try{const r=await e.pick()||"/";if(!r.startsWith("/"))throw new Error(t("files","Invalid path selected"));this.directory=p().normalize(r),await c.default.put((0,o.generateUrl)("/apps/files_sharing/settings/shareFolder"),{shareFolder:this.directory})}catch(e){(0,l.x2)(e.message||t("files","Unknown error"))}},resetFolder(){this.directory=this.defaultDirectory,c.default.delete((0,o.generateUrl)("/apps/files_sharing/settings/shareFolder"))}}};var g=a(93379),A=a.n(g),_=a(7795),m=a.n(_),v=a(90569),y=a.n(v),b=a(3565),C=a.n(b),w=a(19216),x=a.n(w),k=a(44589),S=a.n(k),D=a(34630),j={};j.styleTagTransform=S(),j.setAttributes=C(),j.insert=y().bind(null,"head"),j.domAPI=m(),j.insertStyleElement=x(),A()(D.Z,j),D.Z&&D.Z.locals&&D.Z.locals;var F=a(51900);const O=(0,F.Z)(h,(function(){var e=this,t=e._self._c;return t("div",{staticClass:"share-folder"},[t("span",[e._v(e._s(e.t("files_sharing","Set default folder for accepted shares"))+" ")]),e._v(" "),t("form",{staticClass:"share-folder__form",on:{reset:function(t){return t.preventDefault(),t.stopPropagation(),e.resetFolder.apply(null,arguments)}}},[t("input",{staticClass:"share-folder__picker",attrs:{type:"text",placeholder:e.readableDirectory},on:{click:function(t){return t.preventDefault(),e.pickFolder.apply(null,arguments)}}}),e._v(" "),e.readableDirectory!==e.defaultDirectory?t("input",{staticClass:"share-folder__reset",attrs:{type:"reset","aria-label":e.t("files_sharing","Reset folder to system default")},domProps:{value:e.t("files_sharing","Reset")}}):e._e()])])}),[],!1,null,"1b20ac1d",null).exports;var Z=a(25108);const P={name:"PersonalSettings",components:{SelectShareFolderDialogue:O},data:()=>({accepting:(0,i.j)("files_sharing","accept_default"),enforceAcceptShares:(0,i.j)("files_sharing","enforce_accept"),allowCustomDirectory:(0,i.j)("files_sharing","allow_custom_share_folder")}),methods:{async toggleEnabled(){try{await c.default.put((0,o.generateUrl)("/apps/files_sharing/settings/defaultAccept"),{accept:this.accepting})}catch(e){(0,l.x2)(t("sharing","Error while toggling options")),Z.error(e)}}}};var T=a(99303),E={};E.styleTagTransform=S(),E.setAttributes=C(),E.insert=y().bind(null,"head"),E.domAPI=m(),E.insertStyleElement=x(),A()(T.Z,E),T.Z&&T.Z.locals&&T.Z.locals;const M=(0,F.Z)(P,(function(){var e=this,t=e._self._c;return!e.enforceAcceptShares||e.allowCustomDirectory?t("div",{staticClass:"section",attrs:{id:"files-sharing-personal-settings"}},[t("h2",[e._v(e._s(e.t("files_sharing","Sharing")))]),e._v(" "),e.enforceAcceptShares?e._e():t("p",[t("input",{directives:[{name:"model",rawName:"v-model",value:e.accepting,expression:"accepting"}],staticClass:"checkbox",attrs:{id:"files-sharing-personal-settings-accept",type:"checkbox"},domProps:{checked:Array.isArray(e.accepting)?e._i(e.accepting,null)>-1:e.accepting},on:{change:[function(t){var r=e.accepting,a=t.target,n=!!a.checked;if(Array.isArray(r)){var s=e._i(r,null);a.checked?s<0&&(e.accepting=r.concat([null])):s>-1&&(e.accepting=r.slice(0,s).concat(r.slice(s+1)))}else e.accepting=n},e.toggleEnabled]}}),e._v(" "),t("label",{attrs:{for:"files-sharing-personal-settings-accept"}},[e._v(e._s(e.t("files_sharing","Accept shares from other accounts and groups by default")))])]),e._v(" "),e.allowCustomDirectory?t("p",[t("SelectShareFolderDialogue")],1):e._e()]):e._e()}),[],!1,null,"7756885b",null).exports;a.nc=btoa((0,s.IH)()),n.default.prototype.t=t,(new(n.default.extend(M))).$mount("#files-sharing-personal-settings")},99303:(e,t,r)=>{r.d(t,{Z:()=>i});var a=r(87537),n=r.n(a),s=r(23645),o=r.n(s)()(n());o.push([e.id,"p[data-v-7756885b]{margin-top:12px;margin-bottom:12px}","",{version:3,sources:["webpack://./apps/files_sharing/src/components/PersonalSettings.vue"],names:[],mappings:"AACA,mBACC,eAAA,CACA,kBAAA",sourcesContent:["\np {\n\tmargin-top: 12px;\n\tmargin-bottom: 12px;\n}\n"],sourceRoot:""}]);const i=o},34630:(e,t,r)=>{r.d(t,{Z:()=>i});var a=r(87537),n=r.n(a),s=r(23645),o=r.n(s)()(n());o.push([e.id,".share-folder__form[data-v-1b20ac1d]{display:flex}.share-folder__picker[data-v-1b20ac1d]{cursor:pointer;min-width:266px}.share-folder__reset[data-v-1b20ac1d]{background-color:rgba(0,0,0,0);border:none;font-weight:normal;text-decoration:underline;font-size:inherit}","",{version:3,sources:["webpack://./apps/files_sharing/src/components/SelectShareFolderDialogue.vue"],names:[],mappings:"AAEC,qCACC,YAAA,CAGD,uCACC,cAAA,CACA,eAAA,CAID,sCACC,8BAAA,CACA,WAAA,CACA,kBAAA,CACA,yBAAA,CACA,iBAAA",sourcesContent:["\n.share-folder {\n\t&__form {\n\t\tdisplay: flex;\n\t}\n\n\t&__picker {\n\t\tcursor: pointer;\n\t\tmin-width: 266px;\n\t}\n\n\t// Make the reset button looks like text\n\t&__reset {\n\t\tbackground-color: transparent;\n\t\tborder: none;\n\t\tfont-weight: normal;\n\t\ttext-decoration: underline;\n\t\tfont-size: inherit;\n\t}\n}\n"],sourceRoot:""}]);const i=o}},a={};function n(e){var t=a[e];if(void 0!==t)return t.exports;var s=a[e]={id:e,loaded:!1,exports:{}};return r[e].call(s.exports,s,s.exports,n),s.loaded=!0,s.exports}n.m=r,e=[],n.O=(t,r,a,s)=>{if(!r){var o=1/0;for(d=0;d<e.length;d++){r=e[d][0],a=e[d][1],s=e[d][2];for(var i=!0,l=0;l<r.length;l++)(!1&s||o>=s)&&Object.keys(n.O).every((e=>n.O[e](r[l])))?r.splice(l--,1):(i=!1,s<o&&(o=s));if(i){e.splice(d--,1);var c=a();void 0!==c&&(t=c)}}return t}s=s||0;for(var d=e.length;d>0&&e[d-1][2]>s;d--)e[d]=e[d-1];e[d]=[r,a,s]},n.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return n.d(t,{a:t}),t},n.d=(e,t)=>{for(var r in t)n.o(t,r)&&!n.o(e,r)&&Object.defineProperty(e,r,{enumerable:!0,get:t[r]})},n.g=function(){if("object"==typeof globalThis)return globalThis;try{return this||new Function("return this")()}catch(e){if("object"==typeof window)return window}}(),n.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),n.r=e=>{"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},n.nmd=e=>(e.paths=[],e.children||(e.children=[]),e),n.j=8330,(()=>{n.b=document.baseURI||self.location.href;var e={8330:0};n.O.j=t=>0===e[t];var t=(t,r)=>{var a,s,o=r[0],i=r[1],l=r[2],c=0;if(o.some((t=>0!==e[t]))){for(a in i)n.o(i,a)&&(n.m[a]=i[a]);if(l)var d=l(n)}for(t&&t(r);c<o.length;c++)s=o[c],n.o(e,s)&&e[s]&&e[s][0](),e[s]=0;return n.O(d)},r=self.webpackChunknextcloud=self.webpackChunknextcloud||[];r.forEach(t.bind(null,0)),r.push=t.bind(null,r.push.bind(r))})(),n.nc=void 0;var s=n.O(void 0,[7874],(()=>n(73168)));s=n.O(s)})();
//# sourceMappingURL=files_sharing-personal-settings.js.map?v=e1bc5e591b4805bc10a4