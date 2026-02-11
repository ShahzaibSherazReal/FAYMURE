// Apply TT DRUGS font to all instances of "FAYMURE" text
document.addEventListener('DOMContentLoaded', function() {
    // Function to recursively find and wrap FAYMURE text
    function wrapFaymureText(node) {
        if (!node) return;
        
        // Skip script, style, and already processed elements
        if (node.nodeType === Node.ELEMENT_NODE) {
            if (node.tagName === 'SCRIPT' || node.tagName === 'STYLE' || node.classList.contains('faymure-font')) {
                return;
            }
        }
        
        // Process child nodes first (before modifying parent)
        const childNodes = Array.from(node.childNodes);
        childNodes.forEach(child => {
            wrapFaymureText(child);
        });
        
        // Process text nodes
        if (node.nodeType === Node.TEXT_NODE) {
            const text = node.textContent;
            const regex = /(FAYMURE|faymure)/gi;
            
            if (regex.test(text)) {
                const parent = node.parentNode;
                if (!parent || parent.classList.contains('faymure-font')) return;
                
                const parts = text.split(regex);
                const fragment = document.createDocumentFragment();
                
                parts.forEach(part => {
                    if (part && part.match(/^FAYMURE$/i)) {
                        const span = document.createElement('span');
                        span.className = 'faymure-font';
                        span.textContent = part;
                        fragment.appendChild(span);
                    } else if (part) {
                        fragment.appendChild(document.createTextNode(part));
                    }
                });
                
                parent.replaceChild(fragment, node);
            }
        }
    }
    
    // Apply to entire document body
    wrapFaymureText(document.body);
});

