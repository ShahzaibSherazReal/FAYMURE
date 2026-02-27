(function() {
    'use strict';
    // Drag and drop for block order (post editor will enhance)
    document.querySelectorAll('.blog-editor-blocks').forEach(function(container) {
        if (!container.dataset.blocksInit) return;
        container.dataset.blocksInit = '1';
        var blocks = container.querySelectorAll('.blog-editor-block');
        blocks.forEach(function(block, i) {
            var handle = block.querySelector('.blog-editor-block-handle');
            if (handle) {
                handle.setAttribute('draggable', 'true');
                handle.addEventListener('dragstart', function(e) {
                    e.dataTransfer.setData('text/plain', block.dataset.index || i);
                    block.classList.add('blog-editor-dragging');
                });
                handle.addEventListener('dragend', function() { block.classList.remove('blog-editor-dragging'); });
            }
            block.addEventListener('dragover', function(e) {
                e.preventDefault();
                if (block.classList.contains('blog-editor-dragging')) return;
                block.classList.add('blog-editor-drag-over');
            });
            block.addEventListener('dragleave', function() { block.classList.remove('blog-editor-drag-over'); });
            block.addEventListener('drop', function(e) {
                e.preventDefault();
                block.classList.remove('blog-editor-drag-over');
                var fromIndex = parseInt(e.dataTransfer.getData('text/plain'), 10);
                var toIndex = parseInt(block.dataset.index, 10);
                if (isNaN(fromIndex) || isNaN(toIndex) || fromIndex === toIndex) return;
                var orderInput = document.querySelector('input[name="block_order"]');
                if (orderInput) {
                    var order = JSON.parse(orderInput.value || '[]');
                    var item = order.splice(fromIndex, 1)[0];
                    order.splice(toIndex, 0, item);
                    orderInput.value = JSON.stringify(order);
                }
                location.reload();
            });
        });
    });
})();
