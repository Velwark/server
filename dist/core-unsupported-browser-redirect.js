/*! For license information please see core-unsupported-browser-redirect.js.LICENSE.txt */
(()=>{var e,r={25714:(e,r,o)=>{"use strict";var t=o(79753),n=o(49232),i=o(31e3),l=o.n(i),s=o(77727),a=o.n(s);const u=(0,n.z$)({allowHigherVersions:!0,browsers:a()});l()(a());const d=(0,o(62556).getBuilder)("core").clearOnLogout().persist().build();var c=o(77958),p=o(17499);const f=null===(b=(0,c.ts)())?(0,p.IY)().setApp("core").build():(0,p.IY)().setApp("core").setUid(b.uid).build();var b,v=o(23085).lW;const h=(0,t.generateUrl)("/unsupported"),g="true"===d.getItem("unsupported-browser-ignore");var w,y;window.TESTING||null!==(w=OC)&&void 0!==w&&null!==(y=w.config)&&void 0!==y&&y.no_unsupported_browser_warning||function(){if(u.test(navigator.userAgent))f.debug("this browser is officially supported ! 🚀");else if(g)f.debug("this browser is NOT supported but has been manually overridden ! ⚠️");else if(-1===window.location.pathname.indexOf(h)){const e=window.location.href.replace(window.location.origin,""),r=v.from(e).toString("base64");history.pushState(null,null,"".concat(h,"?redirect_url=").concat(r)),window.location.reload()}}()},72950:()=>{}},o={};function t(e){var n=o[e];if(void 0!==n)return n.exports;var i=o[e]={id:e,loaded:!1,exports:{}};return r[e].call(i.exports,i,i.exports,t),i.loaded=!0,i.exports}t.m=r,e=[],t.O=(r,o,n,i)=>{if(!o){var l=1/0;for(d=0;d<e.length;d++){o=e[d][0],n=e[d][1],i=e[d][2];for(var s=!0,a=0;a<o.length;a++)(!1&i||l>=i)&&Object.keys(t.O).every((e=>t.O[e](o[a])))?o.splice(a--,1):(s=!1,i<l&&(l=i));if(s){e.splice(d--,1);var u=n();void 0!==u&&(r=u)}}return r}i=i||0;for(var d=e.length;d>0&&e[d-1][2]>i;d--)e[d]=e[d-1];e[d]=[o,n,i]},t.n=e=>{var r=e&&e.__esModule?()=>e.default:()=>e;return t.d(r,{a:r}),r},t.d=(e,r)=>{for(var o in r)t.o(r,o)&&!t.o(e,o)&&Object.defineProperty(e,o,{enumerable:!0,get:r[o]})},t.e=()=>Promise.resolve(),t.g=function(){if("object"==typeof globalThis)return globalThis;try{return this||new Function("return this")()}catch(e){if("object"==typeof window)return window}}(),t.o=(e,r)=>Object.prototype.hasOwnProperty.call(e,r),t.r=e=>{"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},t.nmd=e=>(e.paths=[],e.children||(e.children=[]),e),t.j=8876,(()=>{t.b=document.baseURI||self.location.href;var e={8876:0};t.O.j=r=>0===e[r];var r=(r,o)=>{var n,i,l=o[0],s=o[1],a=o[2],u=0;if(l.some((r=>0!==e[r]))){for(n in s)t.o(s,n)&&(t.m[n]=s[n]);if(a)var d=a(t)}for(r&&r(o);u<l.length;u++)i=l[u],t.o(e,i)&&e[i]&&e[i][0](),e[i]=0;return t.O(d)},o=self.webpackChunknextcloud=self.webpackChunknextcloud||[];o.forEach(r.bind(null,0)),o.push=r.bind(null,o.push.bind(o))})(),t.nc=void 0;var n=t.O(void 0,[7874],(()=>t(25714)));n=t.O(n)})();
//# sourceMappingURL=core-unsupported-browser-redirect.js.map?v=f1233121611611a2d8f7