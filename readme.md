# Low Search Tag

An ExpressionEngine add-on for extended Low Search and Solspace Tag compatibility.

## Compatibility & Requirements

Low Search Tag is compatible with **EE 2.4+** and requires **Low Search 2.2+ to 3.0.3** and **Solspace Tag 4+**. Not needed for Low Search 3.0.4, as the [Tags filter](http://gotolow.com/addons/low-search/docs/filters#tags) is built in.

## Installation

- Download and unzip;
- Copy the `low_search_tag` folder to your `system/expressionengine/third_party` directory;
- In your Control Panel, go to Add-Ons &rarr; Extensions and click the Install-link in the Low Search Tag row;
- All set!

## Usage

Once installed, you can use these parameters, either in the Results tag or as fields in your search form:

- `tag_id`
- `tag_id:group_name`
- `tag_name`
- `tag_name:group_name`

**Note:** it is recommended you use `tag_id` instead of `tag_name` for performance reasons.

By default, it will filter by *any* of the tags (a OR b OR c). Use `require_all="tag_id"` to search for *all* tags (a AND b AND c). This translates to using either `tag_id="1|2|3"` and `tag_id="1&2&3"`.

Use the `:group_name` options to combine `AND` and `OR` filtering: (a OR b OR c) AND (x OR y OR z). This translates to using `tag_id:group1="1|2|3" tag_id:group2="4|5|6"`.

## Examples

### Search Form, simple filter with checkboxes

    {exp:tag:cloud}
      <label>
        <input type="checkbox" name="tag_id[]" value="{tag_id}"
        {if tag_id IN ({low_search_tag_id})}checked="checked"{/if} /> {tag}
      </label>
    {/exp:tag:cloud}

### Search Form, multiple filters with select elements

    <select name="tag_id:group1[]" multiple="multiple">
      {exp:tag:cloud tag_group_name="group1"}
        <option value="{tag_id}"{if tag_id IN ({low_search_tag_id:group1})} selected="selected"{/if}>
          {tag}
        </option>
      {/exp:tag:cloud}
    </select>

    <select name="tag_id:group2[]" multiple="multiple">
      {exp:tag:cloud tag_group_name="group2"}
        <option value="{tag_id}"{if tag_id IN ({low_search_tag_id:group2})} selected="selected"{/if}>
          {tag}
        </option>
      {/exp:tag:cloud}
    </select>

### Results tag

    {exp:low_search:results
    	query="{segment_2}"
    	tag_name="tag-one|second-tag"
    	websafe_separator="-"
    }
      ...
    {/exp:low_search:results}

## Links

- [Low Search](http://gotolow.com/addons/low-search)
- [Solspace Tag](http://www.solspace.com/software/detail/tag/?affiliate=91)
- [Download Low Search Tag](https://github.com/lodewijk/low_search_tag/archive/master.zip)