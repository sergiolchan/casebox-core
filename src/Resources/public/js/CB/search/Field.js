Ext.ns('CB');

Ext.define('CB.search.Field', {

    extend: 'Ext.form.field.Text'

    ,xtype: 'CBSearchField'
    ,alias: 'widget.CBSearchField'

    ,emptyText: L.Search
    ,enableKeyEvents: true
    ,style: 'background-color: #fff'

    ,triggers: {
        clear: {
            cls: 'x-form-clear-trigger'
            ,hidden: true
            ,scope: 'this'
            ,handler: 'onTrigger1Click'
        }
        ,search: {
            cls: 'x-form-search-trigger'
            ,scope: 'this'
            ,handler: 'onTrigger2Click'
        }
        ,options: {
            cls: 'x-form-trigger'
            ,scope: 'this'
            ,handler: 'onOptionsTriggerClick'
            ,weight: -1
        }
    }

    ,searchIn: ['name', 'content']

    ,initComponent : function(){
        Ext.apply(this, {
            listeners: {
                scope: this
                ,keyup: function(ed, e){
                    if(Ext.isEmpty(this.getValue())) {
                        this.triggers.clear.hide();

                    } else {
                        this.triggers.clear.show();
                    }
                }
                ,specialkey: function(ed, e){
                    switch(e.getKey()){
                        case e.ESC:
                            this.onTrigger1Click(e);
                            break;
                        case e.ENTER:
                            this.onTrigger2Click(e);
                            break;
                    }
                }
            }
        });

        this.callParent(arguments);
    }

    ,afterRender: function() {
        this.callParent(arguments);
    }

    ,setValue: function(value) {
        this.callParent(arguments);

        if (Ext.isEmpty(value)){
            this.triggers.clear.hide();
        } else {
            this.triggers.clear.show();
        }
    }

    ,clear: function(){
        this.setValue('');
        this.triggers.clear.hide();
    }

    ,onTrigger1Click : function(e){
        if(Ext.isEmpty(this.getValue())) {
            return;
        }

        this.setValue('');
        this.triggers.clear.hide();
        this.fireEvent('search', {query: ''}, e);
    }

    ,onTrigger2Click : function(e){
        this.fireEvent(
            'search'
            ,{
                query: this.getValue()
                ,searchIn: this.searchIn
            }
            ,this
            ,e
        );
    }

    ,onOptionsTriggerClick : function(e){
        if(!this.optionsMenu) {
            var menuItems = [{
                text: L.Title
                ,xtype: 'menucheckitem'
                ,checked: true
                ,searchIn: 'name'
                ,scope: this
                ,handler: this.onTotggleSearchIn
            },{
                text: L.Content
                ,xtype: 'menucheckitem'
                ,checked: true
                ,searchIn: 'content'
                ,scope: this
                ,handler: this.onTotggleSearchIn
            },{
                text: L.Advanced
                ,disabled: true
                ,scope: this
                // ,handler: this.onAdvancedClick
            },'-'

            ];

            //add search templates
            var templates = CB.DB.templates.query('type', 'search');

            templates.each(
                function(t){
                    menuItems.push({
                        iconCls: t.data.iconCls
                        ,data: {template_id: t.data.id}
                        ,text: t.data.title
                        ,scope: this
                        ,handler: this.onSearchTemplateButtonClick
                    });
                }
                ,this
            );

            this.optionsMenu = new Ext.menu.Menu({items: menuItems});
        }
        this.optionsMenu.showAt(e.getXY());
    }

    ,onTotggleSearchIn: function(item, e) {
        if(item.checked) {
            Ext.Array.include(this.searchIn, item.searchIn);
        } else {
            Ext.Array.remove(this.searchIn, item.searchIn);
        }
    }

    ,onSearchTemplateButtonClick: function(b, e) {
        //load default search template if not already loaded
        var config = {
                xtype: 'CBSearchEditWindow'
                ,id: 'sew' + b.config.data.template_id
            };

        config.data = Ext.apply({}, b.config.data);

        var w  = App.windowManager.openWindow(config);
        if(!w.existing) {
            w.alignTo(App.mainViewPort.getEl(), 'bl-bl?');
        }

        delete w.existing;
    }
});
