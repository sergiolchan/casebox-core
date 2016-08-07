Ext.override(Jarvus.ace.Editor, {
    setValue: function(value) {
        return this.getAce().setValue(value);
    }

    ,getValue: function() {
        return this.getAce().getValue();
    }
});
