Ext.namespace('CB.browser.view.grid.toolbar');

Ext.define('CB.browser.view.grid.toolbar.Paging', {
    extend: 'Ext.toolbar.Paging'

    ,xtype: 'CBBrowserViewGridPagingToolbar'

    ,border: false
    ,displayInfo: true
    ,displayMsg: '{0} - {1} of {2}'

    ,initComponent: function(){
        var me = this;

        this._getPagingItems = this.getPagingItems;
        this.getPagingItems = this.getCustomizedPaginItems;

        this.setCustomItems();

        me.callParent();
    }

    ,getCustomizedPaginItems: function() {
        var rez = this._getPagingItems();

        rez.shift(); //remove "first page" button
        rez.pop(); //remove reload button
        rez.pop(); //remove divider
        rez.pop(); //remove "last page" button
        rez.splice(1, 1); // remove splitter
        rez.splice(4, 1); // remove socond splitter

        return rez;
    }

    ,setCustomItems: function() {
        var me = this;
    }
});
