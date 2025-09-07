/**
 * Digitale Signatur Modul für Wohnungsübergabe
 * Open Source Lösung mit Signature Pad
 * Rechtssicher durch Zeitstempel und Metadaten
 */

class SignatureModule {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.options = {
            width: options.width || 600,
            height: options.height || 200,
            backgroundColor: options.backgroundColor || 'rgb(255, 255, 255)',
            penColor: options.penColor || 'rgb(0, 0, 0)',
            ...options
        };
        
        this.signaturePad = null;
        this.signatureData = null;
        this.metadata = {};
        
        this.init();
    }
    
    init() {
        // Canvas erstellen
        const canvas = document.createElement('canvas');
        canvas.width = this.options.width;
        canvas.height = this.options.height;
        canvas.style.border = '2px solid #dee2e6';
        canvas.style.borderRadius = '0.375rem';
        canvas.style.touchAction = 'none';
        
        // Container vorbereiten
        this.container.innerHTML = '';
        
        // Wrapper für Canvas
        const canvasWrapper = document.createElement('div');
        canvasWrapper.className = 'signature-canvas-wrapper mb-3';
        canvasWrapper.appendChild(canvas);
        
        // Buttons
        const buttonGroup = document.createElement('div');
        buttonGroup.className = 'btn-group';
        buttonGroup.innerHTML = `
            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearSignature">
                <i class="bi bi-arrow-counterclockwise"></i> Löschen
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="undoSignature">
                <i class="bi bi-arrow-90deg-left"></i> Rückgängig
            </button>
        `;
        
        // Info Text
        const infoText = document.createElement('div');
        infoText.className = 'small text-muted mt-2';
        infoText.innerHTML = `
            <i class="bi bi-info-circle"></i> 
            Bitte unterschreiben Sie mit der Maus oder auf dem Touchscreen. 
            Die Unterschrift wird verschlüsselt gespeichert.
        `;
        
        // Zusammenbauen
        this.container.appendChild(canvasWrapper);
        this.container.appendChild(buttonGroup);
        this.container.appendChild(infoText);
        
        // SignaturePad initialisieren
        this.signaturePad = new SignaturePad(canvas, {
            backgroundColor: this.options.backgroundColor,
            penColor: this.options.penColor,
            velocityFilterWeight: 0.7,
            minWidth: 1.5,
            maxWidth: 3,
            throttle: 16,
            minDistance: 3
        });
        
        // Event Listener
        document.getElementById('clearSignature').addEventListener('click', () => this.clear());
        document.getElementById('undoSignature').addEventListener('click', () => this.undo());
        
        // Auto-Resize für Mobile
        this.resizeCanvas();
        window.addEventListener('resize', () => this.resizeCanvas());
    }
    
    resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const canvas = this.signaturePad.canvas;
        
        const width = Math.min(this.options.width, this.container.offsetWidth);
        canvas.width = width * ratio;
        canvas.height = this.options.height * ratio;
        canvas.style.width = width + 'px';
        canvas.style.height = this.options.height + 'px';
        
        const context = canvas.getContext('2d');
        context.scale(ratio, ratio);
        
        this.signaturePad.clear();
    }
    
    clear() {
        this.signaturePad.clear();
        this.signatureData = null;
    }
    
    undo() {
        const data = this.signaturePad.toData();
        if (data) {
            data.pop();
            this.signaturePad.fromData(data);
        }
    }
    
    isEmpty() {
        return this.signaturePad.isEmpty();
    }
    
    getSignatureData() {
        if (this.isEmpty()) {
            return null;
        }
        
        // Metadaten sammeln
        this.metadata = {
            timestamp: new Date().toISOString(),
            userAgent: navigator.userAgent,
            screenResolution: `${screen.width}x${screen.height}`,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            language: navigator.language,
            platform: navigator.platform
        };
        
        // Signatur-Daten
        return {
            dataUrl: this.signaturePad.toDataURL('image/png'),
            svgData: this.signaturePad.toDataURL('image/svg+xml'),
            metadata: this.metadata,
            hash: this.generateHash()
        };
    }
    
    generateHash() {
        // Einfacher Hash für Integritätsprüfung
        const data = this.signaturePad.toDataURL();
        let hash = 0;
        for (let i = 0; i < data.length; i++) {
            const char = data.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return Math.abs(hash).toString(16);
    }
    
    loadSignature(dataUrl) {
        if (dataUrl) {
            const img = new Image();
            img.onload = () => {
                this.signaturePad.clear();
                const context = this.signaturePad.canvas.getContext('2d');
                context.drawImage(img, 0, 0);
            };
            img.src = dataUrl;
        }
    }
    
    // Validierung
    validateSignature() {
        if (this.isEmpty()) {
            return {
                valid: false,
                message: 'Bitte unterschreiben Sie im Feld oben.'
            };
        }
        
        // Minimale Anzahl von Strichen prüfen
        const data = this.signaturePad.toData();
        if (data.length < 2) {
            return {
                valid: false,
                message: 'Die Unterschrift scheint unvollständig. Bitte unterschreiben Sie erneut.'
            };
        }
        
        return {
            valid: true,
            message: 'Unterschrift ist gültig.'
        };
    }
    
    // Export als Bild für PDF
    exportForPDF(options = {}) {
        const width = options.width || 200;
        const height = options.height || 80;
        
        // Temporäres Canvas für Export
        const exportCanvas = document.createElement('canvas');
        exportCanvas.width = width;
        exportCanvas.height = height;
        
        const exportContext = exportCanvas.getContext('2d');
        exportContext.fillStyle = 'white';
        exportContext.fillRect(0, 0, width, height);
        
        // Signatur skaliert zeichnen
        const img = new Image();
        img.src = this.signaturePad.toDataURL();
        
        return new Promise((resolve) => {
            img.onload = () => {
                const scale = Math.min(width / img.width, height / img.height) * 0.9;
                const x = (width - img.width * scale) / 2;
                const y = (height - img.height * scale) / 2;
                
                exportContext.drawImage(img, x, y, img.width * scale, img.height * scale);
                resolve(exportCanvas.toDataURL('image/png'));
            };
        });
    }
}

// Signature Pad Library (Minified) - MIT License
// https://github.com/szimek/signature_pad
!function(t,e){"object"==typeof exports&&"undefined"!=typeof module?module.exports=e():"function"==typeof define&&define.amd?define(e):(t=t||self).SignaturePad=e()}(this,function(){"use strict";var t=function(){function t(t,e,i){this.x=t,this.y=e,this.time=i||Date.now()}return t.prototype.distanceTo=function(t){return Math.sqrt(Math.pow(this.x-t.x,2)+Math.pow(this.y-t.y,2))},t.prototype.equals=function(t){return this.x===t.x&&this.y===t.y&&this.time===t.time},t.prototype.velocityFrom=function(t){return this.time!==t.time?this.distanceTo(t)/(this.time-t.time):0},t}(),e=function(){function e(t,e,i,n,o,s){this.startPoint=t,this.control2=e,this.control1=i,this.endPoint=n,this.startWidth=o,this.endWidth=s}return e.fromPoints=function(t,i){var n=this.calculateControlPoints(t[0],t[1],t[2]).c2,o=this.calculateControlPoints(t[1],t[2],t[3]).c1;return new e(t[1],n,o,t[2],i.start,i.end)},e.calculateControlPoints=function(e,i,n){var o=e.x-i.x,s=e.y-i.y,r=i.x-n.x,h=i.y-n.y,a=(e.x+i.x)/2,c=(e.y+i.y)/2,u=(i.x+n.x)/2,l=(i.y+n.y)/2,d=Math.sqrt(o*o+s*s),v=Math.sqrt(r*r+h*h),p=d+v==0?0:v/(d+v),f=u+(a-u)*p,_=l+(c-l)*p,m=i.x-f,g=i.y-_;return{c1:new t(a+m,c+g),c2:new t(u+m,l+g)}},e.prototype.length=function(){for(var t,e,i=0,n=0;n<=10;n+=1){var o=n/10,s=this.point(o,this.startPoint.x,this.control1.x,this.control2.x,this.endPoint.x),r=this.point(o,this.startPoint.y,this.control1.y,this.control2.y,this.endPoint.y);if(n>0){var h=s-t,a=r-e;i+=Math.sqrt(h*h+a*a)}t=s,e=r}return i},e.prototype.point=function(t,e,i,n,o){return e*(1-t)*(1-t)*(1-t)+3*i*(1-t)*(1-t)*t+3*n*(1-t)*t*t+o*t*t*t},e}();return function(){function i(t,e){var i=this;void 0===e&&(e={}),this.canvas=t,this.options=e,this._handleMouseDown=function(t){1===t.which&&(i._mouseButtonDown=!0,i._strokeBegin(t))},this._handleMouseMove=function(t){i._mouseButtonDown&&i._strokeMoveUpdate(t)},this._handleMouseUp=function(t){1===t.which&&i._mouseButtonDown&&(i._mouseButtonDown=!1,i._strokeEnd(t))},this._handleTouchStart=function(t){if(t.preventDefault(),1===t.targetTouches.length){var e=t.changedTouches[0];i._strokeBegin(e)}},this._handleTouchMove=function(t){t.preventDefault();var e=t.targetTouches[0];i._strokeMoveUpdate(e)},this._handleTouchEnd=function(t){t.preventDefault();t.target===i.canvas&&(t.preventDefault(),i._strokeEnd(t))},this.velocityFilterWeight=e.velocityFilterWeight||.7,this.minWidth=e.minWidth||.5,this.maxWidth=e.maxWidth||2.5,this.throttle="throttle"in e?e.throttle:16,this.minDistance="minDistance"in e?e.minDistance:5,this.dotSize=e.dotSize||function(){return(this.minWidth+this.maxWidth)/2},this.penColor=e.penColor||"black",this.backgroundColor=e.backgroundColor||"rgba(0,0,0,0)",this.onBegin=e.onBegin,this.onEnd=e.onEnd,this._strokeMoveUpdate=this.throttle?function(t,e){void 0===e&&(e=250);var i,n,o,s=0,r=null,h=function(){s=Date.now(),r=null,i=t.apply(n,o),r||(n=null,o=[])};return function(){for(var a=[],c=0;c<arguments.length;c++)a[c]=arguments[c];var u=Date.now(),l=e-(u-s);return n=this,o=a,l<=0||l>e?(r&&(clearTimeout(r),r=null),s=u,i=t.apply(n,o),r||(n=null,o=[])):r||(r=window.setTimeout(h,l)),i}}(i.prototype._strokeUpdate,this.throttle):i.prototype._strokeUpdate,this._ctx=t.getContext("2d"),this.clear(),this.on()}return i.prototype.clear=function(){var t=this._ctx,e=this.canvas;t.fillStyle=this.backgroundColor,t.clearRect(0,0,e.width,e.height),t.fillRect(0,0,e.width,e.height),this._data=[],this._reset(),this._isEmpty=!0},i.prototype.fromDataURL=function(t,e,i){var n=this;void 0===e&&(e={});var o=new Image,s=e.ratio||window.devicePixelRatio||1,r=e.width||this.canvas.width/s,h=e.height||this.canvas.height/s;this._reset(),o.onload=function(){n._ctx.drawImage(o,0,0,r,h),i&&i()},o.onerror=function(t){i&&i(t)},o.src=t,this._isEmpty=!1},i.prototype.toDataURL=function(t,e){switch(void 0===t&&(t="image/png"),t){case"image/svg+xml":return this._toSVG();default:return this.canvas.toDataURL(t,e)}},i.prototype.on=function(){this._handleMouseEvents(),this._handleTouchEvents()},i.prototype.off=function(){this.canvas.style.msTouchAction="auto",this.canvas.style.touchAction="auto",this.canvas.removeEventListener("mousedown",this._handleMouseDown),this.canvas.removeEventListener("mousemove",this._handleMouseMove),document.removeEventListener("mouseup",this._handleMouseUp),this.canvas.removeEventListener("touchstart",this._handleTouchStart),this.canvas.removeEventListener("touchmove",this._handleTouchMove),this.canvas.removeEventListener("touchend",this._handleTouchEnd)},i.prototype.isEmpty=function(){return this._isEmpty},i.prototype.fromData=function(t){var e=this;this.clear(),this._fromData(t,function(t){var i=t.color,n=t.curve;return e._drawCurve({color:i,curve:n})},function(t){var i=t.color,n=t.point;return e._drawDot({color:i,point:n})}),this._data=t},i.prototype.toData=function(){return this._data},i.prototype._strokeBegin=function(t){var e={color:this.penColor,points:[this._createPoint(t)]};this._data.push(e),this._reset(),this._strokeUpdate(t),"function"==typeof this.onBegin&&this.onBegin(t)},i.prototype._strokeUpdate=function(t){var e=this._createPoint(t),i=this._addPoint(e),n=i.curve,o=i.widths;n&&this._drawCurve({color:i.color,curve:n,widths:o}),this._data[this._data.length-1].points.push({time:e.time,x:e.x,y:e.y})},i.prototype._strokeEnd=function(t){this._strokeUpdate(t),"function"==typeof this.onEnd&&this.onEnd(t)},i.prototype._handleMouseEvents=function(){this._mouseButtonDown=!1,this.canvas.addEventListener("mousedown",this._handleMouseDown),this.canvas.addEventListener("mousemove",this._handleMouseMove),document.addEventListener("mouseup",this._handleMouseUp)},i.prototype._handleTouchEvents=function(){this.canvas.style.msTouchAction="none",this.canvas.style.touchAction="none",this.canvas.addEventListener("touchstart",this._handleTouchStart),this.canvas.addEventListener("touchmove",this._handleTouchMove),this.canvas.addEventListener("touchend",this._handleTouchEnd)},i.prototype._reset=function(){this._lastPoints=[],this._lastVelocity=0,this._lastWidth=(this.minWidth+this.maxWidth)/2,this._ctx.fillStyle=this.penColor},i.prototype._createPoint=function(e){var i=this.canvas.getBoundingClientRect();return new t(e.clientX-i.left,e.clientY-i.top,(new Date).getTime())},i.prototype._addPoint=function(t){var i=this._lastPoints;if(i.push(t),i.length>2){3===i.length&&i.unshift(i[0]);var n=this._calculateCurveWidths(i[1],i[2]),o=e.fromPoints(i,n);return i.shift(),{curve:o,widths:n}}return{}},i.prototype._calculateCurveWidths=function(t,e){var i=this.velocityFilterWeight*e.velocityFrom(t)+(1-this.velocityFilterWeight)*this._lastVelocity,n=this._strokeWidth(i),o={end:n,start:this._lastWidth};return this._lastVelocity=i,this._lastWidth=n,o},i.prototype._strokeWidth=function(t){return Math.max(this.maxWidth/(t+1),this.minWidth)},i.prototype._drawCurveSegment=function(t,e,i){var n=this._ctx;n.moveTo(t,e),n.arc(t,e,i,0,2*Math.PI,!1),this._isEmpty=!1},i.prototype._drawCurve=function(t){var e=t.color,i=t.curve,n=t.widths,o=this._ctx,s=n.start,r=n.end,h=i.startWidth,a=i.endWidth;2===h&&2===a?(o.beginPath(),this._drawCurveSegment(i.startPoint.x,i.startPoint.y,h),o.closePath(),o.fill()):(o.beginPath(),o.lineWidth=h,o.lineCap="round",o.strokeStyle=e,o.moveTo(i.startPoint.x,i.startPoint.y),o.quadraticCurveTo(i.control1.x,i.control1.y,i.endPoint.x,i.endPoint.y),o.stroke())},i.prototype._drawDot=function(t){var e=t.color,i=t.point,n=this._ctx,o="function"==typeof this.dotSize?this.dotSize():this.dotSize;n.beginPath(),this._drawCurveSegment(i.x,i.y,o),n.closePath(),n.fillStyle=e,n.fill()},i.prototype._fromData=function(e,i,n){for(var o=0,s=e;o<s.length;o++){var r=s[o],h=r.color,a=r.points;if(a.length>1)for(var c=0;c<a.length;c+=1){var u=a[c],l=new t(u.x,u.y,u.time);this.penColor=h,0===c&&this._reset();var d=this._addPoint(l);d.curve&&i({color:h,curve:d.curve,widths:d.widths})}else this._reset(),n({color:h,point:a[0]})}},i.prototype._toSVG=function(){var t=this,e=this._data,i=Math.max(window.devicePixelRatio||1,1),n=this.canvas.width/i,o=this.canvas.height/i,s=document.createElementNS("http://www.w3.org/2000/svg","svg");s.setAttribute("width",this.canvas.width.toString()),s.setAttribute("height",this.canvas.height.toString()),this._fromData(e,function(t){var e=t.color,i=t.curve,r=t.widths,h=document.createElement("path");if(!(isNaN(i.control1.x)||isNaN(i.control1.y)||isNaN(i.control2.x)||isNaN(i.control2.y))){var a="M "+i.startPoint.x.toFixed(3)+","+i.startPoint.y.toFixed(3)+" C "+i.control1.x.toFixed(3)+","+i.control1.y.toFixed(3)+" "+i.control2.x.toFixed(3)+","+i.control2.y.toFixed(3)+" "+i.endPoint.x.toFixed(3)+","+i.endPoint.y.toFixed(3);h.setAttribute("d",a),h.setAttribute("stroke-width",(2.25*r.end).toFixed(3)),h.setAttribute("stroke",e),h.setAttribute("fill","none"),h.setAttribute("stroke-linecap","round"),s.appendChild(h)}},function(e){var i=e.color,r=e.point,h=document.createElement("circle"),a="function"==typeof t.dotSize?t.dotSize():t.dotSize;h.setAttribute("r",a.toString()),h.setAttribute("cx",r.x.toString()),h.setAttribute("cy",r.y.toString()),h.setAttribute("fill",i),s.appendChild(h)});var r='<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 '+n+" "+o+'" width="'+n+'" height="'+o+'">',h=s.innerHTML;if(void 0===h){var a=document.createElement("dummy"),c=s.childNodes;a.innerHTML="";for(var u=0;u<c.length;u+=1)a.appendChild(c[u].cloneNode(!0));h=a.innerHTML}return"data:image/svg+xml;base64,"+window.btoa(r+h+"</svg>")},i}()});
