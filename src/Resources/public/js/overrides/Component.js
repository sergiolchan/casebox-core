Ext.override(Ext.Component, {
    onHide: function() {
        if (this.el) {
            this.callParent(arguments);
        }
    }
});
